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
