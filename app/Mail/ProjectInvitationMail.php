<?php

namespace App\Mail;

use App\Models\ProjectInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProjectInvitation $invitation, public bool $hasAccount = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invitation->project->name} on Luminite",
        );
    }

    public function content(): Content
    {
        $base = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        return new Content(
            markdown: 'emails.project-invitation',
            with: [
                'projectName' => $this->invitation->project->name,
                'inviterName' => $this->invitation->inviter->name,
                'hasAccount'  => $this->hasAccount,
                'actionUrl'   => $this->hasAccount ? "{$base}/" : "{$base}/signup",
            ],
        );
    }
}
