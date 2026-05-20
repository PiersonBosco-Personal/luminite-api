<?php

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
