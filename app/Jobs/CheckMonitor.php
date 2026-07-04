<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\IncidentOpened;
use App\Events\IncidentResolved;
use App\Events\MonitorChecked;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\MonitorStatus;
use App\Support\SsrfGuard;
use Carbon\Carbon;
use GuzzleHttp\TransferStats;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CheckMonitor implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public Monitor $monitor
    ) {
        $this->queue = 'monitors';
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->monitor->id;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'monitor:'.$this->monitor->id,
            'user:'.$this->monitor->user_id,
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Monitor check failed permanently', [
            'monitor_id' => $this->monitor->id,
            'monitor_name' => $this->monitor->name,
            'exception' => $exception?->getMessage(),
        ]);
    }

    public function handle(): void
    {
        $monitor = Monitor::query()->find($this->monitor->id);

        if (! $monitor || ! $monitor->is_active) {
            Log::debug('Skipping check for inactive or deleted monitor', [
                'monitor_id' => $this->monitor->id,
            ]);

            return;
        }

        $this->monitor = $monitor;

        $check = $this->performCheck();

        // Wrap check save, incident handling, and scheduling update in one transaction for atomicity
        DB::transaction(function () use ($check) {
            $this->monitor->checks()->save($check);
            $this->handleStatusChange($check);

            $this->monitor->update([
                'last_checked_at' => $check->checked_at,
                'next_check_at' => $this->calculateNextCheckAt(),
            ]);
        });

        MonitorChecked::dispatch($this->monitor, $check);

        Log::debug('Monitor check completed', [
            'monitor_id' => $this->monitor->id,
            'status' => $check->status,
            'status_code' => $check->status_code,
            'response_time_ms' => $check->response_time_ms,
        ]);
    }

    protected function performCheck(): MonitorCheck
    {
        $check = new MonitorCheck([
            'checked_at' => now(),
        ]);

        $allowedMethods = ['get', 'head'];
        $method = strtolower($this->monitor->method);

        if (! in_array($method, $allowedMethods, true)) {
            $check->status = MonitorStatus::Down->value;
            $check->status_code = 0;
            $check->error_message = "Invalid HTTP method: {$this->monitor->method}";

            return $check;
        }

        try {
            $startTime = microtime(true);
            $resolvedIp = null;

            $response = Http::withOptions([
                'on_stats' => function (TransferStats $stats) use (&$resolvedIp) {
                    $resolvedIp = $stats->getHandlerStat('primary_ip');
                    if ($resolvedIp && (new SsrfGuard)->isBlockedIp($resolvedIp)) {
                        throw new RuntimeException('Request resolved to blocked IP address');
                    }
                },
            ])
                ->timeout($this->monitor->timeout)
                ->connectTimeout(10)
                ->withoutRedirecting()
                ->withUserAgent(config('monitors.user_agent', 'OpenFlare Monitor/1.0'))
                ->{$method}($this->monitor->url);

            $endTime = microtime(true);
            $responseTimeMs = (int) (($endTime - $startTime) * 1000);

            $check->status_code = $response->status();
            $check->response_time_ms = $responseTimeMs;

            if ($response->status() === $this->monitor->expected_status_code) {
                $check->status = MonitorStatus::Up->value;
            } else {
                $check->status = MonitorStatus::Down->value;
                $check->error_message = "Expected status {$this->monitor->expected_status_code}, got {$response->status()}";
            }
        } catch (ConnectionException $e) {
            $check->status = MonitorStatus::Down->value;
            $check->status_code = 0;
            $check->error_message = $this->categorizeConnectionError($e);
        } catch (RequestException $e) {
            $check->status = MonitorStatus::Down->value;
            $check->status_code = $e->response?->status() ?? 0;
            $check->error_message = "Request failed: {$e->getMessage()}";
        } catch (Throwable $e) {
            $check->status = MonitorStatus::Down->value;
            $check->status_code = 0;
            $check->error_message = "Unexpected error: {$e->getMessage()}";

            Log::error('Unexpected monitor check error', [
                'monitor_id' => $this->monitor->id,
                'exception' => $e,
            ]);
        }

        return $check;
    }

    protected function categorizeConnectionError(ConnectionException $e): string
    {
        $message = $e->getMessage();

        if (stripos($message, 'timeout') !== false || stripos($message, 'timed out') !== false) {
            return "Timeout: Request exceeded {$this->monitor->timeout}s limit";
        }

        if (stripos($message, 'could not resolve host') !== false || stripos($message, 'name or service not known') !== false) {
            return 'DNS resolution failed: Could not resolve hostname';
        }

        if (stripos($message, 'connection refused') !== false) {
            return 'Connection refused: Server is not accepting connections';
        }

        if (stripos($message, 'ssl') !== false || stripos($message, 'certificate') !== false) {
            return "SSL/TLS error: {$message}";
        }

        if (stripos($message, 'network is unreachable') !== false) {
            return 'Network unreachable: Cannot reach the server';
        }

        return "Connection failed: {$message}";
    }

    protected function handleStatusChange(MonitorCheck $newCheck): void
    {
        if ($newCheck->isDown()) {
            $currentIncident = Incident::query()
                ->where('monitor_id', $this->monitor->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($currentIncident) {
                return;
            }

            $failureThreshold = $this->getFailureConfirmationThreshold();
            $recentChecks = $this->getRecentChecks($failureThreshold);

            if (! $this->meetsConfirmationThreshold($recentChecks, MonitorStatus::Down, $failureThreshold)) {
                return;
            }

            $startedAt = $recentChecks->sortBy('checked_at')->first()?->checked_at ?? $newCheck->checked_at;

            try {
                $incident = Incident::create([
                    'monitor_id' => $this->monitor->id,
                    'ended_at' => null,
                    'started_at' => $startedAt,
                    'cause' => $newCheck->error_message,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another concurrent worker already opened the incident; nothing to do.
                return;
            }

            IncidentOpened::dispatch($this->monitor, $incident);
            $this->sendNotifications(MonitorStatus::Down, $newCheck);

            return;
        }

        $currentIncident = $this->monitor->currentIncident()->first();

        if (! $currentIncident) {
            return;
        }

        $recoveryThreshold = $this->getRecoveryConfirmationThreshold();
        $recentChecks = $this->getRecentChecks($recoveryThreshold);

        if (! $this->meetsConfirmationThreshold($recentChecks, MonitorStatus::Up, $recoveryThreshold)) {
            return;
        }

        $currentIncident->update([
            'ended_at' => $newCheck->checked_at,
        ]);

        IncidentResolved::dispatch($this->monitor, $currentIncident->fresh());
        $this->sendNotifications(MonitorStatus::Up, $newCheck);
    }

    protected function getRecentChecks(int $limit): Collection
    {
        return $this->monitor->checks()
            ->latest('checked_at')
            ->limit($limit)
            ->get();
    }

    protected function meetsConfirmationThreshold(
        Collection $recentChecks,
        MonitorStatus $status,
        int $threshold
    ): bool {
        if ($recentChecks->count() < $threshold) {
            return false;
        }

        return $recentChecks->every(fn (MonitorCheck $check) => $status === MonitorStatus::Up
            ? $check->isUp()
            : $check->isDown());
    }

    protected function getFailureConfirmationThreshold(): int
    {
        if ($this->monitor->failure_confirmation_threshold !== null) {
            return max(1, (int) $this->monitor->failure_confirmation_threshold);
        }

        return max(1, (int) config('monitors.failure_confirmation_threshold', 3));
    }

    protected function getRecoveryConfirmationThreshold(): int
    {
        if ($this->monitor->recovery_confirmation_threshold !== null) {
            return max(1, (int) $this->monitor->recovery_confirmation_threshold);
        }

        return max(1, (int) config('monitors.recovery_confirmation_threshold', 3));
    }

    protected function sendNotifications(MonitorStatus $status, MonitorCheck $check): void
    {
        $notifiers = $this->monitor->getEffectiveNotifiers()
            ->filter(fn (Notifier $notifier) => $this->notifierHasValidConfig($notifier));

        foreach ($notifiers as $notifier) {
            SendMonitorNotification::dispatch(
                $this->monitor,
                $check,
                $notifier,
                $status
            );
        }

        Log::info('Dispatched notifications', [
            'monitor_id' => $this->monitor->id,
            'status' => $status,
            'notifiers_count' => $notifiers->count(),
        ]);
    }

    protected function notifierHasValidConfig(Notifier $notifier): bool
    {
        return match ($notifier->type) {
            Notifier::TYPE_DISCORD => ! empty($notifier->getWebhookUrl()),
            Notifier::TYPE_EMAIL => ! empty($notifier->getEmail()),
            default => false,
        };
    }

    protected function calculateNextCheckAt(): Carbon
    {
        $scheduledAt = $this->monitor->next_check_at ?? now();
        $nextCheck = $scheduledAt->copy()->addSeconds($this->monitor->interval);

        while ($nextCheck->isPast()) {
            $nextCheck->addSeconds($this->monitor->interval);
        }

        return $nextCheck;
    }
}
