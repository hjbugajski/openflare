<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentOpened implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public Incident $incident
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
            'incident' => [
                'id' => $this->incident->id,
                'started_at' => $this->incident->started_at->toIso8601String(),
                'cause' => $this->incident->cause,
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'incident.opened';
    }
}
