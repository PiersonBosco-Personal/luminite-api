<?php

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskSection;

it('create_task creates a task in the named section with resolved labels', function () {
    [$raw, $token, $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $inprog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'In Progress', 'position' => 1]);
    Label::factory()->create(['project_id' => $project->id, 'name' => 'bug']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'create_task', 'arguments' => [
                'title'    => 'Fix login',
                'priority' => 'high',
                'section'  => 'In Progress',
                'labels'   => ['bug', 'auth'],
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    $task = Task::where('project_id', $project->id)->where('title', 'Fix login')->first();

    expect($text)->toContain('Created task')
        ->and($task)->not->toBeNull()
        ->and($task->section_id)->toBe($inprog->id)
        ->and($task->priority)->toBe('high')
        ->and($task->created_by)->toBe($token->user_id)
        ->and($task->labels->pluck('name')->sort()->values()->all())->toBe(['auth', 'bug']); // 'auth' auto-created
});

it('create_task defaults to the first section when none given', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $first = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 1]);

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 2,
            'params'  => ['name' => 'create_task', 'arguments' => ['title' => 'No section task']],
        ])
        ->assertStatus(200);

    expect(Task::where('title', 'No section task')->first()->section_id)->toBe($first->id);
});

it('creates a subtask under a parent when parent is given', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = \App\Models\TaskSection::create([
        'project_id' => $project->id, 'name' => 'Backlog', 'position' => 0,
    ]);
    $parent = \App\Models\Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'Parent', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 7,
            'params'  => ['name' => 'create_task', 'arguments' => [
                'title'  => 'Child task',
                'parent' => $parent->id,
            ]],
        ])
        ->json('result.content.0.text');

    expect($text)->toContain('Created task');
    expect(\App\Models\Task::where('title', 'Child task')->first()->parent_task_id)->toBe($parent->id);
});

it('rejects a parent task that belongs to a different project', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);

    // Build a second, completely separate project with its own section and task.
    $otherUser    = \App\Models\User::factory()->create();
    $otherProject = createProject($otherUser);
    $otherSection = TaskSection::factory()->create(['project_id' => $otherProject->id, 'position' => 0]);
    $foreignTask  = Task::create([
        'project_id' => $otherProject->id,
        'section_id' => $otherSection->id,
        'title'      => 'Foreign parent',
        'status'     => 'todo',
        'priority'   => 'medium',
        'position'   => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 5,
            'params'  => ['name' => 'create_task', 'arguments' => [
                'title'  => 'Orphan child',
                'parent' => $foreignTask->id,
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain("Error: parent task #{$foreignTask->id}")
        ->and($text)->toContain('not found in this project');

    expect(Task::where('project_id', $project->id)->where('title', 'Orphan child')->exists())->toBeFalse();
});

it('create_task returns an error string for an unknown section', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 3,
            'params'  => ['name' => 'create_task', 'arguments' => ['title' => 'X', 'section' => 'Nope']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Error')->and($text)->toContain('Nope');
});
