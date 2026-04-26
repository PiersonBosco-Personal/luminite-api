<?php

use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;

it('task index includes subtasks_count for each task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $parent  = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'parent_task_id' => $parent->id]);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'parent_task_id' => $parent->id]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/tasks")
        ->assertStatus(200)
        ->json('data');

    $parentInResponse = collect($data)->firstWhere('id', $parent->id);
    expect($parentInResponse['subtasks_count'])->toBe(2);
});

it('deleting a parent task nullifies subtasks parent_task_id', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $parent  = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);
    $subtask = Task::factory()->create([
        'project_id'     => $project->id,
        'section_id'     => $section->id,
        'parent_task_id' => $parent->id,
    ]);

    $this->deleteJson("/api/v1/projects/{$project->id}/tasks/{$parent->id}")
        ->assertStatus(200);

    expect($subtask->fresh()->parent_task_id)->toBeNull();
    expect(Task::find($subtask->id))->not->toBeNull();
});

it('subtask can be unlinked from parent by updating parent_task_id to null', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $parent  = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);
    $subtask = Task::factory()->create([
        'project_id'     => $project->id,
        'section_id'     => $section->id,
        'parent_task_id' => $parent->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$subtask->id}", [
        'parent_task_id' => null,
    ])->assertStatus(200);

    expect($subtask->fresh()->parent_task_id)->toBeNull();
});

it('show returns subtasks with their assignees loaded', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $section  = TaskSection::factory()->create(['project_id' => $project->id]);
    $parent   = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);
    $assignee = User::factory()->create();

    Task::factory()->create([
        'project_id'     => $project->id,
        'section_id'     => $section->id,
        'parent_task_id' => $parent->id,
        'assigned_to'    => $assignee->id,
    ]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/tasks/{$parent->id}")
        ->assertStatus(200);

    $subtask = $response->json('data.subtasks.0');
    expect($subtask)->toHaveKey('assignee')
        ->and($subtask['assignee']['id'])->toBe($assignee->id);
});

it('section index only returns top-level tasks not subtasks', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $parent  = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    Task::factory()->create([
        'project_id'     => $project->id,
        'section_id'     => $section->id,
        'parent_task_id' => $parent->id,
    ]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/sections")
        ->assertStatus(200)
        ->json('data');

    $sectionInResponse = collect($data)->firstWhere('id', $section->id);
    expect(count($sectionInResponse['tasks']))->toBe(1)
        ->and($sectionInResponse['tasks'][0]['id'])->toBe($parent->id);
});
