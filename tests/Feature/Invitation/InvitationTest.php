<?php

use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeInvitation(array $overrides = []): ProjectInvitation
{
    $owner   = User::factory()->create();
    $project = createProject($owner);

    return ProjectInvitation::create(array_merge([
        'project_id' => $project->id,
        'invited_by' => $owner->id,
        'email'      => 'invitee@example.com',
        'token'      => Str::random(64),
        'expires_at' => Carbon::now()->addDays(7),
    ], $overrides));
}

// ── GET /api/v1/invitations/{token} ──────────────────────────────────────────

it('returns invitation details for a valid pending token', function () {
    $invitation = makeInvitation();

    $this->getJson("/api/v1/invitations/{$invitation->token}")
        ->assertStatus(200)
        ->assertJsonStructure(['data' => ['email', 'project_id', 'project_name', 'inviter_name']])
        ->assertJsonFragment(['email' => 'invitee@example.com']);
});

it('returns 404 for an expired token', function () {
    $invitation = makeInvitation(['expires_at' => Carbon::now()->subDay()]);

    $this->getJson("/api/v1/invitations/{$invitation->token}")
        ->assertStatus(404);
});

it('returns 404 for an already accepted token', function () {
    $invitation = makeInvitation(['accepted_at' => Carbon::now()->subHour()]);

    $this->getJson("/api/v1/invitations/{$invitation->token}")
        ->assertStatus(404);
});

it('returns 404 for a token that does not exist', function () {
    $this->getJson('/api/v1/invitations/' . Str::random(64))
        ->assertStatus(404);
});

// ── POST /api/v1/invitations/{token}/accept ───────────────────────────────────

it('accepts invitation: creates user, joins project, returns auth token', function () {
    $invitation = makeInvitation();

    $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name'                  => 'New User',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email'], 'project_id']);

    // User was created
    $user = User::where('email', 'invitee@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('New User');

    // User was added to the project
    $project = Project::find($response->json('project_id'));
    expect($project->members()->where('user_id', $user->id)->exists())->toBeTrue();

    // Invitation marked accepted
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('returns a valid sanctum token after accepting', function () {
    $invitation = makeInvitation();

    $token = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name'                  => 'New User',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/user')
        ->assertStatus(200)
        ->assertJsonFragment(['email' => 'invitee@example.com']);
});

it('returns 404 when accepting an expired invitation', function () {
    $invitation = makeInvitation(['expires_at' => Carbon::now()->subDay()]);

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name'                  => 'New User',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(404);
});

it('returns 404 when accepting an already accepted invitation', function () {
    $invitation = makeInvitation(['accepted_at' => Carbon::now()->subHour()]);

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name'                  => 'New User',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(404);
});

it('returns 409 when the invited email already has an account', function () {
    User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = makeInvitation();

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name'                  => 'New User',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(409);
});

it('returns 422 when name is missing on accept', function () {
    $invitation = makeInvitation();

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422);
});

it('returns 422 when password is too short on accept', function () {
    $invitation = makeInvitation();

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name'                  => 'New User',
        'password'              => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422);
});

it('returns 422 when passwords do not match on accept', function () {
    $invitation = makeInvitation();

    $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
        'name'                  => 'New User',
        'password'              => 'password123',
        'password_confirmation' => 'different456',
    ])->assertStatus(422);
});
