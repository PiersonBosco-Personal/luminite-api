<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

// --- List Members ---

it('member can list project members', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/projects/{$project->id}/members")
         ->assertStatus(200)
         ->assertJsonStructure(['data']);
});

it('returns 403 when non-member tries to list members', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/members")->assertStatus(403);
});

// --- Add Member (now always creates an invitation) ---

it('owner inviting an existing user creates an invitation and does NOT auto-add', function () {
    Mail::fake();
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $newUser = User::factory()->create();

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => $newUser->email,
    ])->assertStatus(202)->assertJsonFragment(['message' => 'Invitation sent.']);

    expect($project->members()->where('user_id', $newUser->id)->exists())->toBeFalse();
    expect(\App\Models\ProjectInvitation::where('email', $newUser->email)
        ->where('project_id', $project->id)->exists())->toBeTrue();

    Mail::assertSent(\App\Mail\ProjectInvitationMail::class);
});

it('broadcasts InvitationCreated to an existing invited user', function () {
    Mail::fake();
    Event::fake([\App\Events\InvitationCreated::class]);

    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);
    $newUser = User::factory()->create();

    $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => $newUser->email])
        ->assertStatus(202);

    Event::assertDispatched(\App\Events\InvitationCreated::class,
        fn ($e) => $e->recipientUserId === $newUser->id);
});

it('returns 409 when user is already a member', function () {
    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => $member->email,
    ])->assertStatus(409);
});

it('returns 403 when non-owner member tries to add a member', function () {
    ['project' => $project, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($member);
    $newUser = User::factory()->create();

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => $newUser->email,
    ])->assertStatus(403);
});

it('sends an invitation when email does not belong to an existing user', function () {
    Mail::fake();
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => 'newperson@example.com',
    ])->assertStatus(202)->assertJsonFragment(['message' => 'Invitation sent.']);

    Mail::assertSent(\App\Mail\ProjectInvitationMail::class);
    expect(\App\Models\ProjectInvitation::where('email', 'newperson@example.com')->exists())->toBeTrue();
});

it('is idempotent when a pending invitation already exists for that email', function () {
    Mail::fake();
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => 'newperson@example.com'])
        ->assertStatus(202);
    $this->postJson("/api/v1/projects/{$project->id}/members", ['email' => 'newperson@example.com'])
        ->assertStatus(202)->assertJsonFragment(['message' => 'Invitation already sent.']);

    Mail::assertSentCount(1);
    expect(\App\Models\ProjectInvitation::where('email', 'newperson@example.com')->count())->toBe(1);
});

it('returns 422 when email field is missing on add member', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/projects/{$project->id}/members", [])->assertStatus(422);
});

// --- Remove Member ---

it('owner can remove a member', function () {
    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/projects/{$project->id}/members/{$member->id}")
         ->assertStatus(200);

    expect($project->members()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('broadcasts RemovedFromProject to the removed user', function () {
    Event::fake([\App\Events\RemovedFromProject::class]);
    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/projects/{$project->id}/members/{$member->id}")
         ->assertStatus(200);

    Event::assertDispatched(\App\Events\RemovedFromProject::class,
        fn ($e) => $e->userId === $member->id && $e->project->id === $project->id);
});

it('returns 422 when owner tries to remove themselves', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/projects/{$project->id}/members/{$owner->id}")
         ->assertStatus(422);
});

it('returns 403 when non-owner tries to remove a member', function () {
    ['project' => $project, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($member);

    $this->deleteJson("/api/v1/projects/{$project->id}/members/{$member->id}")
         ->assertStatus(403);
});
