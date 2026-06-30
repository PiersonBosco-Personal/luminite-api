<?php

namespace App\Events;

use App\Http\Resources\TaskCompletionResource;
use App\Models\TaskCompletion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCompletionRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TaskCompletion $completion,
        public readonly int $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'TaskCompletionRecorded';
    }

    public function broadcastWith(): array
    {
        return [
            'completion' => (new TaskCompletionResource(
                $this->completion->loadMissing('task', 'completedBy')
            ))->resolve(),
        ];
    }
}
