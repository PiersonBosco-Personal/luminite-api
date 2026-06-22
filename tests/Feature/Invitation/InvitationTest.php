<?php

use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makePendingInvitationFor(string $email, ?Project $project = null): ProjectInvitation
{
    $owner   = User::factory()->create();
    $project ??= createProject($owner);

    return ProjectInvitation::create([
        'project_id' => $project->id,
        'invited_by' => $owner->id,
        'email'      => $email,
        'expires_at' => Carbon::now()->addDays(7),
    ]);
}

// ── GET /api/v1/invitations ───────────────────────────────────────────────────

it('lists pending invitations matching the authenticated user email', function () {
    $user = actingAsUser(['email' => 'invitee@example.com']);
    makePendingInvitationFor('invitee@example.com');
    makePendingInvitationFor('someone-else@example.com');

    $this->getJson('/api/v1/invitations')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['email' => 'invitee@example.com']);
});

it('does not list declined, accepted, or expired invitations', function () {
    actingAsUser(['email' => 'invitee@example.com']);
    makePendingInvitationFor('invitee@example.com')->update(['declined_at' => now()]);
    makePendingInvitationFor('invitee@example.com')->update(['accepted_at' => now()]);
    makePendingInvitationFor('invitee@example.com')->update(['expires_at' => now()->subDay()]);

    $this->getJson('/api/v1/invitations')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('requires authentication to list invitations', function () {
    $this->getJson('/api/v1/invitations')->assertStatus(401);
});

// ── POST /api/v1/invitations/{invitation}/accept ──────────────────────────────

it('accepts an invitation: joins the project and marks accepted', function () {
    Event::fake([\App\Events\InvitationStatusChanged::class]);
    $user = actingAsUser(['email' => 'invitee@example.com']);
    $invitation = makePendingInvitationFor('invitee@example.com');

    $this->postJson("/api/v1/invitations/{$invitation->id}/accept")
        ->assertStatus(200)
        ->assertJsonFragment(['project_id' => $invitation->project_id]);

    expect($invitation->project->members()->where('user_id', $user->id)->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    Event::assertDispatched(\App\Events\InvitationStatusChanged::class);
});

it('forbids accepting an invitation addressed to a different email', function () {
    actingAsUser(['email' => 'me@example.com']);
    $invitation = makePendingInvitationFor('other@example.com');

    $this->postJson("/api/v1/invitations/{$invitation->id}/accept")->assertStatus(403);
});

it('returns 410 when accepting a non-pending invitation', function () {
    actingAsUser(['email' => 'invitee@example.com']);
    $invitation = makePendingInvitationFor('invitee@example.com');
    $invitation->update(['declined_at' => now()]);

    $this->postJson("/api/v1/invitations/{$invitation->id}/accept")->assertStatus(410);
});

it('accept is safe when the user is already a member', function () {
    $user = actingAsUser(['email' => 'invitee@example.com']);
    $invitation = makePendingInvitationFor('invitee@example.com');
    $invitation->project->members()->attach($user->id, ['role' => 'member']);

    $this->postJson("/api/v1/invitations/{$invitation->id}/accept")->assertStatus(200);
    expect($invitation->project->members()->where('user_id', $user->id)->count())->toBe(1);
});

// ── POST /api/v1/invitations/{invitation}/decline ─────────────────────────────

it('declines an invitation: marks declined and does not join', function () {
    Event::fake([\App\Events\InvitationStatusChanged::class]);
    $user = actingAsUser(['email' => 'invitee@example.com']);
    $invitation = makePendingInvitationFor('invitee@example.com');

    $this->postJson("/api/v1/invitations/{$invitation->id}/decline")->assertStatus(200);

    expect($invitation->fresh()->declined_at)->not->toBeNull();
    expect($invitation->project->members()->where('user_id', $user->id)->exists())->toBeFalse();
    Event::assertDispatched(\App\Events\InvitationStatusChanged::class);
});

it('returns 410 when declining a non-pending invitation', function () {
    actingAsUser(['email' => 'invitee@example.com']);
    $invitation = makePendingInvitationFor('invitee@example.com');
    $invitation->update(['accepted_at' => now()]);

    $this->postJson("/api/v1/invitations/{$invitation->id}/decline")->assertStatus(410);
});

it('forbids declining an invitation addressed to a different email', function () {
    actingAsUser(['email' => 'me@example.com']);
    $invitation = makePendingInvitationFor('other@example.com');

    $this->postJson("/api/v1/invitations/{$invitation->id}/decline")->assertStatus(403);
});
