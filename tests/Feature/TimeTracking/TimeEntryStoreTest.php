<?php

use App\Models\Task;
use App\Models\WorkType;

it('creates a manual time entry', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $task     = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $workType = WorkType::factory()->create(['project_id' => $project->id]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/time-entries", [
        'task_id'          => $task->id,
        'work_type_id'     => $workType->id,
        'duration_minutes' => 90,
        'description'      => 'Refactored auth',
        'logged_at'        => '2026-05-19',
    ])->assertStatus(201);

    expect($response->json('data.duration_minutes'))->toBe(90);
    expect($response->json('data.user_id'))->toBe($user->id);
    expect($response->json('data.is_running'))->toBeFalse();
});

it('defaults logged_at to today when omitted', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/time-entries", [
        'task_id'          => $task->id,
        'duration_minutes' => 30,
    ])->assertStatus(201);

    expect($response->json('data.logged_at'))->toBe(today()->toDateString());
});

it('rejects a manual entry without duration_minutes', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->postJson("/api/v1/projects/{$project->id}/time-entries", [
        'task_id' => $task->id,
    ])->assertStatus(422)->assertJsonValidationErrors('duration_minutes');
});
