<?php

namespace App\Events;

use App\Http\Resources\TaskSectionResource;
use App\Models\TaskSection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SectionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TaskSection $section,
        public readonly int         $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'section.created';
    }

    public function broadcastWith(): array
    {
        return ['section' => (new TaskSectionResource($this->section))->resolve()];
    }
}
