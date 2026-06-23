<?php

use App\Models\Project;

it('soft-deletes a project instead of removing the row', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $project->delete();

    expect(Project::find($project->id))->toBeNull();
    expect(Project::withTrashed()->find($project->id))->not->toBeNull();
    expect(Project::withTrashed()->find($project->id)->deleted_at)->not->toBeNull();
});

it('excludes trashed projects from the index listing', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->getJson('/api/v1/projects')->assertStatus(200)->assertJsonCount(1, 'data');

    $project->delete();

    $this->getJson('/api/v1/projects')->assertStatus(200)->assertJsonCount(0, 'data');
});

it('returns 404 for any access to a trashed project', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $project->delete();

    $this->getJson("/api/v1/projects/{$project->id}")->assertStatus(404);
    $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'x'])->assertStatus(404);
});
