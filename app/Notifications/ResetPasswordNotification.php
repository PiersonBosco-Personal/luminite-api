<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

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
