<?php

use App\Models\User;
use App\Models\WorkType;

it('lists work types for a project', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Development']);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Testing']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/work-types")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(2);
});

it('does not list work types from other projects', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $other   = createProject(User::factory()->create());

    WorkType::factory()->create(['project_id' => $other->id]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/work-types")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(0);
});

it('returns 403 on work type index when not a member', function () {
    actingAsUser();
    $project = createProject(User::factory()->create());

    $this->getJson("/api/v1/projects/{$project->id}/work-types")->assertStatus(403);
});
