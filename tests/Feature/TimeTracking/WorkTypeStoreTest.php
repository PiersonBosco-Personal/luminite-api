<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;

it('creates a work type', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $response = $this->postJson("/api/v1/projects/{$project->id}/work-types", [
        'name' => 'Code Review',
    ])->assertStatus(201);

    expect($response->json('data.name'))->toBe('Code Review');
    expect(WorkType::where('project_id', $project->id)->where('name', 'Code Review')->exists())->toBeTrue();
});

it('requires a name when creating a work type', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/work-types", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('returns 403 when storing a work type as a non-member', function () {
    actingAsUser();
    $project = createProject(User::factory()->create());

    $this->postJson("/api/v1/projects/{$project->id}/work-types", ['name' => 'X'])
        ->assertStatus(403);
});

it('returns 422 when an active work type with this name already exists', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Design']);

    $this->postJson("/api/v1/projects/{$project->id}/work-types", ['name' => 'Design'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('matches names case-insensitively when checking for duplicates', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Design']);

    $this->postJson("/api/v1/projects/{$project->id}/work-types", ['name' => 'DESIGN'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('reactivates an inactive work type with the same name instead of creating a duplicate', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $archived = WorkType::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Design',
        'is_active'  => false,
    ]);

    TimeEntry::factory()->count(2)->create([
        'project_id'   => $project->id,
        'user_id'      => $user->id,
        'work_type_id' => $archived->id,
    ]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/work-types", [
        'name' => 'Design',
    ])->assertStatus(200);

    expect($response->json('data.id'))->toBe($archived->id);
    expect($response->json('data.is_active'))->toBeTrue();
    expect($response->json('data.time_entries_count'))->toBe(2);
    expect(WorkType::where('project_id', $project->id)->where('name', 'Design')->count())->toBe(1);
});

it('adopts the new casing when reactivating an inactive work type', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $archived = WorkType::factory()->create([
        'project_id' => $project->id,
        'name'       => 'design',
        'is_active'  => false,
    ]);

    $this->postJson("/api/v1/projects/{$project->id}/work-types", [
        'name' => 'Design',
    ])->assertStatus(200);

    expect($archived->fresh()->name)->toBe('Design');
});
