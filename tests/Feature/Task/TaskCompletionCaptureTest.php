<?php

use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\TaskSection;

it('records a human completion with optional what_changed on status to done', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'status' => 'todo']);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'status'       => 'done',
        'what_changed' => 'Swapped confirm() for a shadcn dialog',
    ])->assertStatus(200);

    $completion = TaskCompletion::where('task_id', $task->id)->latest('id')->first();
    expect($completion)->not->toBeNull()
        ->and($completion->source)->toBe('human')
        ->and($completion->completed_by_user_id)->toBe($user->id)
        ->and($completion->summary_what)->toBe('Swapped confirm() for a shadcn dialog');
});

it('records a human completion even when what_changed is omitted', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'status' => 'todo']);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['status' => 'done'])
        ->assertStatus(200);

    $completion = TaskCompletion::where('task_id', $task->id)->latest('id')->first();
    expect($completion)->not->toBeNull()
        ->and($completion->summary_what)->toBeNull();
});

it('does not record a completion when reopening a done task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'status' => 'done']);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['status' => 'todo'])
        ->assertStatus(200);

    expect(TaskCompletion::where('task_id', $task->id)->count())->toBe(0);
});
