<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;

it('aggregates time entries by work type', function () {
    $user      = actingAsUser();
    $project   = createProject($user);
    $task      = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $dev       = WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Development']);
    $testing   = WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Testing']);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'work_type_id' => $dev->id,     'duration_minutes' => 120, 'logged_at' => today()]);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'work_type_id' => $dev->id,     'duration_minutes' => 60,  'logged_at' => today()]);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'work_type_id' => $testing->id, 'duration_minutes' => 30,  'logged_at' => today()]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/time-entries/report?group_by=work_type")
        ->assertStatus(200);

    expect($response->json('total_minutes'))->toBe(210);
    $groups = collect($response->json('groups'))->keyBy('label');
    expect($groups['Development']['minutes'])->toBe(180);
    expect($groups['Testing']['minutes'])->toBe(30);
});

it('respects from/to date filter in report', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-01']);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'duration_minutes' => 30, 'logged_at' => '2026-05-15']);

    $response = $this->getJson("/api/v1/projects/{$project->id}/time-entries/report?group_by=user&from=2026-05-10&to=2026-05-20")
        ->assertStatus(200);

    expect($response->json('total_minutes'))->toBe(30);
});

it('aggregates by user', function () {
    $userA   = actingAsUser();
    $userB   = User::factory()->create();
    $project = createProject($userA);
    addMemberToProject($project, $userB);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $userA->id]);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $userA->id, 'duration_minutes' => 120]);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $userB->id, 'duration_minutes' => 60]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/time-entries/report?group_by=user")
        ->assertStatus(200);

    $groups = collect($response->json('groups'))->keyBy('id');
    expect($groups[$userA->id]['minutes'])->toBe(120);
    expect($groups[$userB->id]['minutes'])->toBe(60);
});

it('buckets deleted-task entries under Deleted task when grouping by task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'duration_minutes' => 30]);
    $task->delete();
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => null, 'user_id' => $user->id, 'duration_minutes' => 90]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/time-entries/report?group_by=task")
        ->assertStatus(200);

    $deleted = collect($response->json('groups'))->firstWhere('id', null);
    expect($deleted['label'])->toBe('Deleted task');
    expect($deleted['minutes'])->toBe(120);
});
