<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkType;

it('starts a new timer for the user on a task', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $task     = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $workType = WorkType::factory()->create(['project_id' => $project->id]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/time-entries/timer/start", [
        'task_id'      => $task->id,
        'work_type_id' => $workType->id,
    ])->assertStatus(201);

    expect($response->json('data.is_running'))->toBeTrue();
    expect($response->json('data.duration_minutes'))->toBeNull();
    expect($response->json('data.started_at'))->not->toBeNull();
});

it('returns 409 when the user already has an active timer anywhere', function () {
    $user      = actingAsUser();
    $projectA  = createProject($user);
    $projectB  = createProject($user);
    $taskA     = Task::factory()->create(['project_id' => $projectA->id, 'created_by' => $user->id]);
    $taskB     = Task::factory()->create(['project_id' => $projectB->id, 'created_by' => $user->id]);

    TimeEntry::factory()->running()->create([
        'project_id' => $projectA->id,
        'task_id'    => $taskA->id,
        'user_id'    => $user->id,
    ]);

    $this->postJson("/api/v1/projects/{$projectB->id}/time-entries/timer/start", [
        'task_id' => $taskB->id,
    ])->assertStatus(409);
});

it('allows different users to have active timers concurrently', function () {
    $userA = actingAsUser();
    $userB = \App\Models\User::factory()->create();
    $project = createProject($userA);
    addMemberToProject($project, $userB);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $userA->id]);

    TimeEntry::factory()->running()->create([
        'project_id' => $project->id,
        'task_id'    => $task->id,
        'user_id'    => $userB->id,
    ]);

    $this->postJson("/api/v1/projects/{$project->id}/time-entries/timer/start", [
        'task_id' => $task->id,
    ])->assertStatus(201);
});
