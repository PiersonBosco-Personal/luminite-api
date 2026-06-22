<?php

use App\Mail\ProjectInvitationMail;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;

function buildInvitation(): ProjectInvitation
{
    $owner = User::factory()->create(['name' => 'Owner Person']);
    $project = createProject($owner, ['name' => 'Acme']);
    return ProjectInvitation::create([
        'project_id' => $project->id,
        'invited_by' => $owner->id,
        'email'      => 'invitee@example.com',
        'expires_at' => Carbon::now()->addDays(7),
    ])->load('project', 'inviter');
}

it('renders a sign-in CTA when the invitee already has an account', function () {
    config(['app.frontend_url' => 'https://app.test']);
    $mail = (new ProjectInvitationMail(buildInvitation(), hasAccount: true));
    $rendered = $mail->render();

    expect($rendered)->toContain('Sign in')
        ->and($rendered)->toContain('https://app.test');
});

it('renders a sign-up CTA when the invitee has no account', function () {
    config(['app.frontend_url' => 'https://app.test']);
    $mail = (new ProjectInvitationMail(buildInvitation(), hasAccount: false));
    $rendered = $mail->render();

    expect($rendered)->toContain('Sign up')
        ->and($rendered)->toContain('https://app.test/signup');
});
