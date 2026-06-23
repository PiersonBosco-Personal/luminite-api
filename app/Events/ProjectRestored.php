<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectRestored implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param int[] $memberIds */
    public function __construct(
        public readonly int   $projectId,
        public readonly array $memberIds,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("project.{$this->projectId}")];

        foreach ($this->memberIds as $id) {
            $channels[] = new PrivateChannel("user.{$id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'project.restored';
    }

    public function broadcastWith(): array
    {
        return ['project_id' => $this->projectId];
    }
}
