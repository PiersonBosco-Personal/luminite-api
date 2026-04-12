<?php

use App\Models\User;
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

// --- Add Member ---

it('owner can add a member by email', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $newUser = User::factory()->create();

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => $newUser->email,
    ])->assertStatus(200);

    expect($project->members()->where('user_id', $newUser->id)->exists())->toBeTrue();
});

it('returns 403 when non-owner member tries to add a member', function () {
    ['project' => $project, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($member);

    $newUser = User::factory()->create();

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => $newUser->email,
    ])->assertStatus(403);
});

it('returns 409 when user is already a member', function () {
    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => $member->email,
    ])->assertStatus(409);
});

it('returns 422 when email does not exist in users table', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/projects/{$project->id}/members", [
        'email' => 'nonexistent@example.com',
    ])->assertStatus(422);
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
