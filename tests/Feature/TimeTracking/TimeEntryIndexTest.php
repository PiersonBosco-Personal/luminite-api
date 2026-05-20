<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;

it('lists time entries for a project ordered by logged_at desc', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $task     = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $workType = WorkType::factory()->create(['project_id' => $project->id]);

    $older = TimeEntry::factory()->create([
        'project_id'   => $project->id,
        'task_id'      => $task->id,
        'user_id'      => $user->id,
        'work_type_id' => $workType->id,
        'logged_at'    => today()->subDay(),
    ]);
    $newer = TimeEntry::factory()->create([
        'project_id'   => $project->id,
        'task_id'      => $task->id,
        'user_id'      => $user->id,
        'work_type_id' => $workType->id,
        'logged_at'    => today(),
    ]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/time-entries")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(2);
    expect($data[0]['id'])->toBe($newer->id);
    expect($data[1]['id'])->toBe($older->id);
});

it('filters time entries by task_id', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $taskA   = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $taskB   = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $taskA->id, 'user_id' => $user->id]);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $taskB->id, 'user_id' => $user->id]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/time-entries?task_id={$taskA->id}")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['task_id'])->toBe($taskA->id);
});

it('filters time entries by date range', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'logged_at' => '2026-05-01']);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'logged_at' => '2026-05-10']);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'logged_at' => '2026-05-20']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/time-entries?from=2026-05-05&to=2026-05-15")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(1);
});

it('returns 403 on index when not a project member', function () {
    actingAsUser();
    $project = createProject(User::factory()->create());

    $this->getJson("/api/v1/projects/{$project->id}/time-entries")->assertStatus(403);
});
