<?php

namespace App\Events;

use App\Http\Resources\McpHistoryResource;
use App\Models\McpHistory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpActivityCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly McpHistory $history,
        public readonly int        $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'mcp.activity.created';
    }

    public function broadcastWith(): array
    {
        return ['activity' => (new McpHistoryResource($this->history->loadMissing('user')))->resolve()];
    }
}
