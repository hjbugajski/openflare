<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\MonitorStatusChanged;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\MonitorStatus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMonitorNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 300;

    public function __construct(
        public Monitor $monitor,
        public MonitorCheck $check,
        public Notifier $notifier,
        public MonitorStatus $status
    ) {
        $this->queue = 'notifications';
    }

    /**
     * Get the unique ID for the job.
     * Prevents duplicate notifications for the same monitor+notifier+status+check.
     */
    public function uniqueId(): string
    {
        return "notification:{$this->monitor->id}:{$this->notifier->id}:{$this->status->value}:{$this->check->id}";
    }

    public function backoff(): array
    {
        return [5, 15, 30];
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
            'notifier:'.$this->notifier->id,
            'user:'.$this->monitor->user_id,
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Notification failed permanently', [
            'monitor_id' => $this->monitor->id,
            'notifier_id' => $this->notifier->id,
            'status' => $this->status->value,
            'exception' => $exception?->getMessage(),
        ]);
    }

    public function handle(): void
    {
        match ($this->notifier->type) {
            Notifier::TYPE_DISCORD => $this->sendDiscord(),
            Notifier::TYPE_EMAIL => $this->sendEmail(),
            default => null,
        };
    }

    protected function sendDiscord(): void
    {
        $webhookUrl = $this->notifier->getWebhookUrl();

        if (! $webhookUrl) {
            return;
        }

        $isDown = $this->status === MonitorStatus::Down;
        $color = $isDown ? 0xED4245 : 0x57F287; // Red for down, green for up

        $embed = [
            'title' => $isDown ? 'Monitor Down' : 'Monitor Up',
            'description' => $this->monitor->name,
            'color' => $color,
            'fields' => [
                [
                    'name' => 'URL',
                    'value' => $this->monitor->url,
                    'inline' => false,
                ],
                [
                    'name' => 'Status',
                    'value' => $isDown ? 'Down' : 'Up',
                    'inline' => true,
                ],
            ],
            'timestamp' => $this->check->checked_at->toIso8601String(),
        ];

        if ($this->check->status_code) {
            $embed['fields'][] = [
                'name' => 'Status Code',
                'value' => (string) $this->check->status_code,
                'inline' => true,
            ];
        }

        if ($this->check->response_time_ms) {
            $embed['fields'][] = [
                'name' => 'Response Time',
                'value' => $this->check->response_time_ms.'ms',
                'inline' => true,
            ];
        }

        if ($isDown && $this->check->error_message) {
            $embed['fields'][] = [
                'name' => 'Error',
                'value' => mb_substr($this->check->error_message, 0, 1024),
                'inline' => false,
            ];
        }

        try {
            $response = Http::timeout(10)
                ->retry(2, 100)
                ->post($webhookUrl, ['embeds' => [$embed]]);

            if ($response->failed()) {
                throw new \Exception("Discord webhook failed: {$response->status()}");
            }
        } catch (Throwable $e) {
            Log::error('Discord notification failed', [
                'monitor_id' => $this->monitor->id,
                'notifier_id' => $this->notifier->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function sendEmail(): void
    {
        $email = $this->notifier->getEmail();

        if (! $email) {
            return;
        }

        Mail::to($email)->send(new MonitorStatusChanged(
            $this->monitor,
            $this->check,
            $this->status
        ));
    }
}
