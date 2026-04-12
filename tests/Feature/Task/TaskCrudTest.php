<?php

use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;

// --- Index ---

it('returns tasks ordered by position', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'B', 'position' => 1]);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'A', 'position' => 0]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/tasks")
        ->assertStatus(200)
        ->json('data');

    expect($data[0]['title'])->toBe('A')
        ->and($data[1]['title'])->toBe('B');
});

it('returns 403 on index when not a member', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/tasks")->assertStatus(403);
});

// --- Store ---

it('member can create a task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/tasks", [
        'title'      => 'New Task',
        'section_id' => $section->id,
        'status'     => 'todo',
        'priority'   => 'high',
    ])->assertStatus(201)->assertJsonFragment(['title' => 'New Task']);
});

it('auto-increments position per section on create', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'position' => 0]);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'position' => 1]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/tasks", [
        'title'      => 'Third',
        'section_id' => $section->id,
    ])->assertStatus(201);

    expect($response->json('data.position'))->toBe(2);
});

it('can create a subtask via parent_task_id', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $parent  = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/tasks", [
        'title'          => 'Subtask',
        'section_id'     => $section->id,
        'parent_task_id' => $parent->id,
    ])->assertStatus(201);

    expect($response->json('data.parent_task_id'))->toBe($parent->id);
});

it('returns 422 when section_id is missing on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task'])
        ->assertStatus(422);
});

it('returns 422 when status enum is invalid on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/tasks", [
        'title'      => 'Task',
        'section_id' => $section->id,
        'status'     => 'invalid_status',
    ])->assertStatus(422);
});

// --- Show ---

it('member can view a task with subtasks', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'parent_task_id' => $task->id]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertStatus(200);

    expect($response->json('data'))->toHaveKey('subtasks')
        ->and($response->json('data.subtasks'))->toHaveCount(1);
});

it('returns 404 when task belongs to another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $section  = TaskSection::factory()->create(['project_id' => $project2->id]);
    $task     = Task::factory()->create(['project_id' => $project2->id, 'section_id' => $section->id]);

    $this->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertStatus(404);
});

// --- Update ---

it('member can update a task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'title'  => 'Updated',
        'status' => 'done',
    ])->assertStatus(200)->assertJsonFragment(['title' => 'Updated']);
});

it('returns 404 when updating a task from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $section  = TaskSection::factory()->create(['project_id' => $project2->id]);
    $task     = Task::factory()->create(['project_id' => $project2->id, 'section_id' => $section->id]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", ['title' => 'X'])
        ->assertStatus(404);
});

// --- Destroy ---

it('member can delete a task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertStatus(200);

    expect(Task::find($task->id))->toBeNull();
});

it('returns 404 when deleting a task from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $section  = TaskSection::factory()->create(['project_id' => $project2->id]);
    $task     = Task::factory()->create(['project_id' => $project2->id, 'section_id' => $section->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertStatus(404);
});

// --- Reorder ---

it('member can reorder tasks across sections', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $s1      = TaskSection::factory()->create(['project_id' => $project->id]);
    $s2      = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $s1->id, 'position' => 0]);

    $this->postJson("/api/v1/projects/{$project->id}/tasks/reorder", [
        'tasks' => [
            ['id' => $task->id, 'section_id' => $s2->id, 'position' => 0],
        ],
    ])->assertStatus(200);

    expect($task->fresh()->section_id)->toBe($s2->id)
        ->and($task->fresh()->position)->toBe(0);
});

it('returns 422 when tasks payload is missing on reorder', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/tasks/reorder", [])
        ->assertStatus(422);
});
