<?php

use App\Models\TimeEntry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkType;

it('reports a running timer as running and a logged entry as not running', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    $running = TimeEntry::factory()->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'task_id'          => Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id])->id,
        'work_type_id'     => WorkType::factory()->create(['project_id' => $project->id])->id,
        'duration_minutes' => null,
        'started_at'       => now(),
        'stopped_at'       => null,
    ]);

    $logged = TimeEntry::factory()->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'task_id'          => Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id])->id,
        'work_type_id'     => WorkType::factory()->create(['project_id' => $project->id])->id,
        'duration_minutes' => 60,
    ]);

    expect($running->isRunning())->toBeTrue();
    expect($logged->isRunning())->toBeFalse();
});

it('scope active returns only entries with running timers', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    TimeEntry::factory()->count(3)->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'task_id'          => Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id])->id,
        'duration_minutes' => 30,
    ]);
    TimeEntry::factory()->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'task_id'          => Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id])->id,
        'duration_minutes' => null,
        'started_at'       => now(),
    ]);

    expect(TimeEntry::active()->count())->toBe(1);
});

it('sets task_id to null when the task is deleted', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $entry   = TimeEntry::factory()->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'task_id'          => $task->id,
        'duration_minutes' => 30,
    ]);

    $task->delete();

    expect($entry->fresh()->task_id)->toBeNull();
});
