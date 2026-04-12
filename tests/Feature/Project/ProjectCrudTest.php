<?php

use App\Models\Project;
use App\Models\User;

// --- Index ---

it('returns only projects the user is a member of', function () {
    $user  = actingAsUser();
    $other = User::factory()->create();

    $mine  = createProject($user);
    createProject($other); // should not appear

    $this->getJson('/api/v1/projects')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['id' => $mine->id]);
});

it('returns an empty array when user has no projects', function () {
    actingAsUser();

    $this->getJson('/api/v1/projects')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns 401 on index when unauthenticated', function () {
    $this->getJson('/api/v1/projects')->assertStatus(401);
});

// --- Store ---

it('owner can create a project and is auto-added as owner member', function () {
    $user = actingAsUser();

    $response = $this->postJson('/api/v1/projects', [
        'name'        => 'My Project',
        'description' => 'A test project.',
        'status'      => 'active',
    ]);

    $response->assertStatus(201)->assertJsonFragment(['name' => 'My Project']);

    $project = Project::where('name', 'My Project')->first();
    expect($project->members()->where('user_id', $user->id)->wherePivot('role', 'owner')->exists())->toBeTrue();
});

it('returns 422 when name is missing on store', function () {
    actingAsUser();

    $this->postJson('/api/v1/projects', [])->assertStatus(422);
});

it('returns 401 on store when unauthenticated', function () {
    $this->postJson('/api/v1/projects', ['name' => 'Test'])->assertStatus(401);
});

// --- Show ---

it('member can view a project', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->getJson("/api/v1/projects/{$project->id}")
        ->assertStatus(200)
        ->assertJsonFragment(['id' => $project->id]);
});

it('returns 403 when non-member tries to view a project', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}")->assertStatus(403);
});

it('returns 401 on show when unauthenticated', function () {
    $project = createProject(User::factory()->create());

    $this->getJson("/api/v1/projects/{$project->id}")->assertStatus(401);
});

// --- Update ---

it('owner can update a project', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->putJson("/api/v1/projects/{$project->id}", [
        'name'   => 'Updated Name',
        'status' => 'archived',
    ])->assertStatus(200)->assertJsonFragment(['name' => 'Updated Name']);
});

it('returns 403 when member (non-owner) tries to update a project', function () {
    ['project' => $project, 'member' => $member] = createProjectWithMember();
    actingAsUser(); // different user — but we need to act as $member

    // Re-auth as the member
    \Laravel\Sanctum\Sanctum::actingAs($member);

    $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hacked'])
        ->assertStatus(403);
});

it('returns 422 when status is invalid on update', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->putJson("/api/v1/projects/{$project->id}", [
        'name'   => 'Valid Name',
        'status' => 'invalid_status',
    ])->assertStatus(422);
});

// --- Destroy ---

it('owner can delete a project', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->deleteJson("/api/v1/projects/{$project->id}")->assertStatus(200);

    expect(Project::find($project->id))->toBeNull();
});

it('returns 403 when member tries to delete a project', function () {
    ['project' => $project, 'member' => $member] = createProjectWithMember();
    \Laravel\Sanctum\Sanctum::actingAs($member);

    $this->deleteJson("/api/v1/projects/{$project->id}")->assertStatus(403);
});
