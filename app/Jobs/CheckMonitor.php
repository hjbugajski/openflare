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
use GuzzleHttp\TransferStats;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CheckMonitor implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    /**
     * IPv4 ranges that are blocked for SSRF protection.
     * Validated at request time to prevent DNS rebinding attacks.
     */
    private const BLOCKED_IPV4_RANGES = [
        '10.0.0.0/8',        // Private (RFC 1918)
        '172.16.0.0/12',     // Private (RFC 1918)
        '192.168.0.0/16',    // Private (RFC 1918)
        '127.0.0.0/8',       // Loopback
        '169.254.0.0/16',    // Link-local & AWS/Azure metadata
        '0.0.0.0/8',         // "This" network
        '100.64.0.0/10',     // Carrier-grade NAT (RFC 6598)
        '192.0.0.0/24',      // IETF Protocol Assignments
        '192.0.2.0/24',      // TEST-NET-1 (documentation)
        '198.51.100.0/24',   // TEST-NET-2 (documentation)
        '203.0.113.0/24',    // TEST-NET-3 (documentation)
        '224.0.0.0/4',       // Multicast
        '240.0.0.0/4',       // Reserved for future use
        '255.255.255.255/32', // Broadcast
    ];

    public function __construct(
        public Monitor $monitor
    ) {
        $this->queue = 'monitors';
    }

    public function backoff(): array
    {
        return [10, 30, 60];
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
        $monitor = Monitor::find($this->monitor->id);

        if (! $monitor || ! $monitor->is_active) {
            Log::debug('Skipping check for inactive or deleted monitor', [
                'monitor_id' => $this->monitor->id,
            ]);

            return;
        }

        $this->monitor = $monitor;

        $check = $this->performCheck();
        $previousCheck = $this->monitor->latestCheck;

        // Wrap check save and incident handling in transaction for consistency
        DB::transaction(function () use ($previousCheck, $check) {
            $this->monitor->checks()->save($check);
            $this->handleStatusChange($previousCheck, $check);
        });

        $this->monitor->update([
            'last_checked_at' => $check->checked_at,
            'next_check_at' => $this->calculateNextCheckAt(),
        ]);

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
                    if ($resolvedIp && $this->isBlockedIp($resolvedIp)) {
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
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $check->status = MonitorStatus::Down->value;
            $check->status_code = 0;
            $check->error_message = $this->categorizeConnectionError($e);
        } catch (\Illuminate\Http\Client\RequestException $e) {
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

    protected function categorizeConnectionError(\Illuminate\Http\Client\ConnectionException $e): string
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

    /**
     * Check if an IP address is blocked (private/internal networks).
     * Used at request time to prevent DNS rebinding attacks.
     */
    protected function isBlockedIp(string $ip): bool
    {
        // Check IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isBlockedIpv4($ip);
        }

        // Check IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isBlockedIpv6($ip);
        }

        return false;
    }

    protected function isBlockedIpv4(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        foreach (self::BLOCKED_IPV4_RANGES as $range) {
            [$subnet, $bits] = explode('/', $range);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - (int) $bits);

            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }

        return false;
    }

    protected function isBlockedIpv6(string $ip): bool
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        $hex = bin2hex($packed);

        // Loopback (::1)
        if ($hex === '00000000000000000000000000000001') {
            return true;
        }

        // Unspecified (::)
        if ($hex === '00000000000000000000000000000000') {
            return true;
        }

        // Link-local (fe80::/10)
        if (str_starts_with($hex, 'fe8') || str_starts_with($hex, 'fe9') ||
            str_starts_with($hex, 'fea') || str_starts_with($hex, 'feb')) {
            return true;
        }

        // Unique local (fc00::/7)
        $firstByte = hexdec(substr($hex, 0, 2));
        if ($firstByte >= 0xFC && $firstByte <= 0xFD) {
            return true;
        }

        // IPv4-mapped (::ffff:0:0/96) - check embedded IPv4
        if (str_starts_with($hex, '00000000000000000000ffff')) {
            $ipv4Hex = substr($hex, 24, 8);
            $ipv4 = long2ip((int) hexdec($ipv4Hex));

            return $this->isBlockedIpv4($ipv4);
        }

        // 6to4 addresses (2002::/16) - check embedded IPv4
        if (str_starts_with($hex, '2002')) {
            $ipv4Hex = substr($hex, 4, 8);
            $ipv4 = long2ip((int) hexdec($ipv4Hex));

            return $this->isBlockedIpv4($ipv4);
        }

        return false;
    }

    protected function handleStatusChange(?MonitorCheck $previousCheck, MonitorCheck $newCheck): void
    {
        $wasUp = $previousCheck?->isUp() ?? true;
        $isUp = $newCheck->isUp();

        if ($wasUp && ! $isUp) {
            // Use firstOrCreate to prevent race condition with concurrent workers
            $incident = Incident::firstOrCreate(
                [
                    'monitor_id' => $this->monitor->id,
                    'ended_at' => null,
                ],
                [
                    'started_at' => $newCheck->checked_at,
                    'cause' => $newCheck->error_message,
                ]
            );

            if ($incident->wasRecentlyCreated) {
                IncidentOpened::dispatch($this->monitor, $incident);
                $this->sendNotifications(MonitorStatus::Down, $newCheck);
            }
        }

        if (! $wasUp && $isUp) {
            $currentIncident = $this->monitor->currentIncident;

            if ($currentIncident && $currentIncident->ended_at === null) {
                $currentIncident->update([
                    'ended_at' => $newCheck->checked_at,
                ]);

                IncidentResolved::dispatch($this->monitor, $currentIncident->fresh());
                $this->sendNotifications(MonitorStatus::Up, $newCheck);
            }
        }
    }

    protected function sendNotifications(MonitorStatus $status, MonitorCheck $check): void
    {
        $notifiers = $this->monitor->notifiers()
            ->where('is_active', true)
            ->get()
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

    /**
     * Calculate the next check time based on the scheduled time, not execution time.
     *
     * This prevents timing drift when job execution is delayed by queue processing.
     */
    protected function calculateNextCheckAt(): \Carbon\Carbon
    {
        $scheduledAt = $this->monitor->next_check_at ?? now();
        $nextCheck = $scheduledAt->copy()->addSeconds($this->monitor->interval);

        // If we've drifted behind (next check is in the past), catch up to the next future slot
        while ($nextCheck->isPast()) {
            $nextCheck->addSeconds($this->monitor->interval);
        }

        return $nextCheck;
    }
}
