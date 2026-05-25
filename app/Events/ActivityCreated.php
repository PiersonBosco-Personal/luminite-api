<?php

namespace App\Events;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ActivityLog $activityLog,
        public readonly int         $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'activity.created';
    }

    public function broadcastWith(): array
    {
        return ['activity' => (new ActivityLogResource($this->activityLog->loadMissing('user')))->resolve()];
    }
}
