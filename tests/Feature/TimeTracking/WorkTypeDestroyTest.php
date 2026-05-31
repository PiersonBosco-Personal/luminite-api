<?php

use App\Models\WorkType;

it('deletes a work type', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $type    = WorkType::factory()->create(['project_id' => $project->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/work-types/{$type->id}")
        ->assertStatus(200);

    expect(WorkType::find($type->id))->toBeNull();
});

it('returns 404 when deleting a work type from another project', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $other   = createProject($user);
    $type    = WorkType::factory()->create(['project_id' => $other->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/work-types/{$type->id}")
        ->assertStatus(404);
});
