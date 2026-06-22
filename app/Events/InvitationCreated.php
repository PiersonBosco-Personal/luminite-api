<?php

namespace App\Events;

use App\Models\ProjectInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ProjectInvitation $invitation,
        public readonly int               $recipientUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->recipientUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'invitation.created';
    }

    public function broadcastWith(): array
    {
        return [
            'invitation' => [
                'id'           => $this->invitation->id,
                'project_id'   => $this->invitation->project_id,
                'project_name' => $this->invitation->project->name,
            ],
        ];
    }
}
