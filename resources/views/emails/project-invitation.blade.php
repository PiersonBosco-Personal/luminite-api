@component('mail::message')
# You've been invited to join {{ $projectName }}

**{{ $inviterName }}** has invited you to collaborate on **{{ $projectName }}** on Luminite.

@component('mail::button', ['url' => $inviteLink])
Accept Invitation
@endcomponent

This invite link expires in 7 days. If you did not expect this invitation, you can ignore this email.

Thanks,
The Luminite Team
@endcomponent
