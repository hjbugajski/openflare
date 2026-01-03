<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Monitor;
use App\Models\MonitorCheck;
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
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('monitors.'.$this->monitor->id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
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

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'monitor.checked';
    }
}
