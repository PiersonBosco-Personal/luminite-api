<?php

namespace App\Events;

use App\Models\ProjectInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ProjectInvitation $invitation,
        public readonly int               $projectId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("project.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'invitation.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'invitation' => [
                'id'     => $this->invitation->id,
                'status' => $this->invitation->status,
            ],
        ];
    }
}
