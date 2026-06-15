<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpTokenUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Token management (create/revoke) for a project. Carries no token material —
     * subscribers just refetch the `mcp-tokens` / `mcp-stats` queries for the project.
     */
    public function __construct(
        public readonly int    $projectId,
        public readonly int    $tokenId,
        public readonly string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'mcp.token.updated';
    }

    public function broadcastWith(): array
    {
        return ['token_id' => $this->tokenId, 'action' => $this->action];
    }
}
