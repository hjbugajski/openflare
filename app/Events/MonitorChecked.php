<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitorChecked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public MonitorCheck $check
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->monitor->user_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'monitor_id' => $this->monitor->id,
            'check' => [
                'id' => $this->check->id,
                'status' => $this->check->status,
                'status_code' => $this->check->status_code,
                'response_time_ms' => $this->check->response_time_ms,
                'error_message' => $this->check->error_message,
                'checked_at' => $this->check->checked_at->toIso8601String(),
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'monitor.checked';
    }
}
