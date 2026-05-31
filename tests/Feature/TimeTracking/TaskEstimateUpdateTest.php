<?php

use App\Models\Task;

it('accepts estimated_minutes on the task update endpoint', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'estimated_minutes' => 240,
    ])->assertStatus(200);

    expect($task->fresh()->estimated_minutes)->toBe(240);
});

it('rejects negative estimated_minutes', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'estimated_minutes' => -10,
    ])->assertStatus(422)->assertJsonValidationErrors('estimated_minutes');
});

it('allows clearing estimated_minutes by passing null', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'estimated_minutes' => 60]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'estimated_minutes' => null,
    ])->assertStatus(200);

    expect($task->fresh()->estimated_minutes)->toBeNull();
});
