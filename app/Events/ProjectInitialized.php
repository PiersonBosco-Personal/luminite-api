<?php

namespace App\Events;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when a blank project is initialized in a single atomic call
 * (see App\Mcp\Tools\InitializeProject). Because that one operation creates
 * details, tech stack, sections, labels, tasks, and dashboard widgets at once,
 * clients listen for this single event and refetch every affected surface,
 * rather than reacting to a burst of per-entity create events.
 */
class ProjectInitialized implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly int     $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'project.initialized';
    }

    public function broadcastWith(): array
    {
        return ['project' => (new ProjectResource($this->project->loadMissing('owner')))->resolve()];
    }
}
