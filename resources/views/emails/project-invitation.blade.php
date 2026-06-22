@component('mail::message')
# You've been invited to join {{ $projectName }}

**{{ $inviterName }}** has invited you to collaborate on **{{ $projectName }}** on Luminite.

@if ($hasAccount)
Sign in to Luminite and you'll find this invitation waiting in your notifications, ready to accept or decline.

@component('mail::button', ['url' => $actionUrl])
Sign in to respond
@endcomponent
@else
Sign up for Luminite using **this email address** and the invitation will be waiting in your notifications, ready to accept.

@component('mail::button', ['url' => $actionUrl])
Sign up to get started
@endcomponent
@endif

This invitation expires in 7 days. If you did not expect it, you can safely ignore this email.

Thanks,
The Luminite Team
@endcomponent
