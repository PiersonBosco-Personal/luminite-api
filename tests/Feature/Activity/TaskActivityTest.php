<?php

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;

it('logs task.created when a task is stored', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/tasks", [
        'title'      => 'Build login page',
        'section_id' => $section->id,
    ])->assertStatus(201);

    $log = ActivityLog::first();
    expect($log)->not->toBeNull()
        ->and($log->event_type)->toBe('task.created')
        ->and($log->subject_label)->toBe('Build login page')
        ->and($log->user_id)->toBe($user->id);
});

it('logs task.deleted when a task is destroyed', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Build login page']);

    $this->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('task.deleted')
        ->and($log->subject_label)->toBe('Build login page')
        ->and($log->subject_id)->toBeNull(); // deleted — no reference
});

it('logs task.completed when status changes to done', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'status' => 'todo', 'title' => 'Build login page']);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['status' => 'done'])
        ->assertStatus(200);

    expect(ActivityLog::where('event_type', 'task.completed')->count())->toBe(1);
});

it('logs task.reopened when status changes from done to non-done', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'status' => 'done', 'title' => 'Build login page']);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['status' => 'in_progress'])
        ->assertStatus(200);

    expect(ActivityLog::where('event_type', 'task.reopened')->count())->toBe(1);
});

it('does not log when status changes between non-done values', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'status' => 'todo']);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['status' => 'in_progress'])
        ->assertStatus(200);

    expect(ActivityLog::count())->toBe(0);
});

it('logs task.assigned when assigned_to is set', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $section  = TaskSection::factory()->create(['project_id' => $project->id]);
    $assignee = User::factory()->create(['name' => 'Jordan']);
    $task     = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Build login page', 'assigned_to' => null]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['assigned_to' => $assignee->id])
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('task.assigned')
        ->and($log->description)->toContain('Jordan')
        ->and($log->description)->toContain('Build login page');
});

it('logs task.unassigned when assigned_to is cleared', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $section  = TaskSection::factory()->create(['project_id' => $project->id]);
    $assignee = User::factory()->create(['name' => 'Jordan']);
    $task     = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Build login page', 'assigned_to' => $assignee->id]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['assigned_to' => null])
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('task.unassigned');
});

it('logs task.due_date_changed when due_date is modified', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'due_date' => '2026-12-01']);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['due_date' => '2026-12-15'])
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('task.due_date_changed')
        ->and($log->old_value)->toBe('2026-12-01')
        ->and($log->new_value)->toBe('2026-12-15')
        ->and($log->debounce_key)->not->toBeNull();
});

it('does not log title-only updates', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['title' => 'New title'])
        ->assertStatus(200);

    expect(ActivityLog::count())->toBe(0);
});
