<?php

namespace App\Events;

use App\Http\Resources\TimeEntryResource;
use App\Http\Resources\UserResource;
use App\Models\TimeEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimeEntryLogged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TimeEntry $entry,
        public readonly int       $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'time_entry.logged';
    }

    public function broadcastWith(): array
    {
        $entry = $this->entry->loadMissing('user', 'workType', 'task');

        return [
            'entry' => (new TimeEntryResource($entry))->resolve(),
            'user'  => (new UserResource($entry->user))->resolve(),
        ];
    }
}
