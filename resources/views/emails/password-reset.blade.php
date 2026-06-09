@component('mail::message')
# Reset your password

Hi {{ $name }},

We received a request to reset the password for your Luminite account.
Click the button below to choose a new password. This link expires in 60 minutes.

@component('mail::button', ['url' => $resetUrl])
Reset Password
@endcomponent

If you didn't request a password reset, you can safely ignore this email — your
password will stay the same.

Thanks,<br>
The Luminite Team
@endcomponent
