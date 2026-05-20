<?php

use App\Models\WorkType;

it('updates a work type', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $type    = WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Old']);

    $this->putJson("/api/v1/projects/{$project->id}/work-types/{$type->id}", [
        'name'      => 'New',
        'is_active' => false,
    ])->assertStatus(200);

    expect($type->fresh()->name)->toBe('New');
    expect($type->fresh()->is_active)->toBeFalse();
});

it('returns 404 when work type does not belong to the project', function () {
    $user      = actingAsUser();
    $project   = createProject($user);
    $other     = createProject($user);
    $foreign   = WorkType::factory()->create(['project_id' => $other->id]);

    $this->putJson("/api/v1/projects/{$project->id}/work-types/{$foreign->id}", ['name' => 'X'])
        ->assertStatus(404);
});
