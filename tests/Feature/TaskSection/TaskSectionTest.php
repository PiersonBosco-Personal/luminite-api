<?php

use App\Models\TaskSection;
use App\Models\User;

// --- Index ---

it('returns task sections ordered by position', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'C', 'position' => 2]);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'A', 'position' => 0]);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'B', 'position' => 1]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/sections")
                 ->assertStatus(200)
                 ->json('data');

    expect($data[0]['name'])->toBe('A')
        ->and($data[1]['name'])->toBe('B')
        ->and($data[2]['name'])->toBe('C');
});

it('returns 403 on index when not a member', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/sections")->assertStatus(403);
});

// --- Store ---

it('member can create a task section', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/sections", ['name' => 'Backlog'])
         ->assertStatus(201)
         ->assertJsonFragment(['name' => 'Backlog']);
});

it('auto-increments position on create', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    TaskSection::factory()->create(['project_id' => $project->id, 'position' => 1]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/sections", ['name' => 'New'])
                     ->assertStatus(201);

    expect($response->json('data.position'))->toBe(2);
});

it('returns 422 when name is missing on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/sections", [])->assertStatus(422);
});

// --- Update ---

it('member can rename a task section', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->putJson("/api/v1/projects/{$project->id}/sections/{$section->id}", ['name' => 'Renamed'])
         ->assertStatus(200)
         ->assertJsonFragment(['name' => 'Renamed']);
});

it('returns 404 when updating a section from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $section  = TaskSection::factory()->create(['project_id' => $project2->id]);

    $this->putJson("/api/v1/projects/{$project->id}/sections/{$section->id}", ['name' => 'X'])
         ->assertStatus(404);
});

// --- Destroy ---

it('member can delete a task section', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/sections/{$section->id}")
         ->assertStatus(200);

    expect(TaskSection::find($section->id))->toBeNull();
});

it('returns 404 when deleting a section from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $section  = TaskSection::factory()->create(['project_id' => $project2->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/sections/{$section->id}")
         ->assertStatus(404);
});

// --- Reorder ---

it('member can reorder task sections', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $s1 = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    $s2 = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 1]);

    $this->postJson("/api/v1/projects/{$project->id}/sections/reorder", [
        'sections' => [
            ['id' => $s1->id, 'position' => 1],
            ['id' => $s2->id, 'position' => 0],
        ],
    ])->assertStatus(200);

    expect($s1->fresh()->position)->toBe(1)
        ->and($s2->fresh()->position)->toBe(0);
});

it('returns 422 when sections payload is missing on reorder', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/sections/reorder", [])
         ->assertStatus(422);
});
