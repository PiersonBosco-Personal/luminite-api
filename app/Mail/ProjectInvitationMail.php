<?php

namespace App\Mail;

use App\Models\ProjectInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Retry a transient SMTP failure a few times before giving up. */
    public int $tries = 3;

    public function __construct(public ProjectInvitation $invitation, public bool $hasAccount = false) {}

    /** Exponential-ish backoff between retries, in seconds. */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * Called by the queue worker when all retries are exhausted. The HTTP
     * request has long since returned, so this is the only place a permanently
     * failed invitation email surfaces — log it loudly for operators.
     */
    public function failed(Throwable $e): void
    {
        Log::error('ProjectInvitationMail permanently failed to send', [
            'invitation_id' => $this->invitation->id,
            'email'         => $this->invitation->email,
            'exception'     => $e->getMessage(),
        ]);
    }

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
