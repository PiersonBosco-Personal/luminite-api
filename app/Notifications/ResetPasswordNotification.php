<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry a transient SMTP failure a few times before giving up. */
    public int $tries = 3;

    public function __construct(public string $token) {}

    /** Exponential-ish backoff between retries, in seconds. */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * Called by the queue worker when all retries are exhausted. Log it so a
     * user who never receives their reset email leaves an operator trail — but
     * never log the token itself.
     */
    public function failed(Throwable $e): void
    {
        Log::error('ResetPasswordNotification permanently failed to send', [
            'exception' => $e->getMessage(),
        ]);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $link = $frontend
            . '/reset-password/' . $this->token
            . '?email=' . urlencode($notifiable->getEmailForPasswordReset());

        $broker = config('auth.defaults.passwords');
        $expiry = config("auth.passwords.{$broker}.expire");

        return (new MailMessage)
            ->subject('Reset your Luminite password')
            ->markdown('emails.password-reset', [
                'resetUrl' => $link,
                'name'     => $notifiable->name,
                'expiry'   => $expiry,
            ]);
    }
}
