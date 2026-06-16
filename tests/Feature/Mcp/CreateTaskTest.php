<?php

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;

it('create_task creates a task in the named section with resolved labels', function () {
    [$raw, $token, $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $inprog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'In Progress', 'position' => 1]);
    Label::factory()->create(['project_id' => $project->id, 'name' => 'bug']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => ['name' => 'create_task', 'arguments' => [
                'title' => 'Fix login',
                'priority' => 'high',
                'section' => 'In Progress',
                'labels' => ['bug', 'auth'],
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    $task = Task::where('project_id', $project->id)->where('title', 'Fix login')->first();

    expect($text)->toContain('Created')
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
            'method' => 'tools/call',
            'id' => 2,
            'params' => ['name' => 'create_task', 'arguments' => ['title' => 'No section task']],
        ])
        ->assertStatus(200);

    expect(Task::where('title', 'No section task')->first()->section_id)->toBe($first->id);
});

it('creates a subtask under a parent when parent is given', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::create([
        'project_id' => $project->id, 'name' => 'Backlog', 'position' => 0,
    ]);
    $parent = Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'Parent', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 7,
            'params' => ['name' => 'create_task', 'arguments' => [
                'title' => 'Child task',
                'parent' => $parent->id,
            ]],
        ])
        ->json('result.content.0.text');

    expect($text)->toContain('"Child task"');
    expect(Task::where('title', 'Child task')->first()->parent_task_id)->toBe($parent->id);
});

it('rejects a parent task that belongs to a different project', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);

    // Build a second, completely separate project with its own section and task.
    $otherUser = User::factory()->create();
    $otherProject = createProject($otherUser);
    $otherSection = TaskSection::factory()->create(['project_id' => $otherProject->id, 'position' => 0]);
    $foreignTask = Task::create([
        'project_id' => $otherProject->id,
        'section_id' => $otherSection->id,
        'title' => 'Foreign parent',
        'status' => 'todo',
        'priority' => 'medium',
        'position' => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 5,
            'params' => ['name' => 'create_task', 'arguments' => [
                'title' => 'Orphan child',
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
            'method' => 'tools/call',
            'id' => 3,
            'params' => ['name' => 'create_task', 'arguments' => ['title' => 'X', 'section' => 'Nope']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Error')->and($text)->toContain('Nope');
});

it('create_task creates the parent plus a child per subtasks entry', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 20,
            'params' => ['name' => 'create_task', 'arguments' => [
                'title' => 'Build auth',
                'section' => 'Backlog',
                'subtasks' => ['Add login form', 'Wire Sanctum'],
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    $parent = Task::where('project_id', $project->id)->where('title', 'Build auth')->first();
    $children = Task::where('parent_task_id', $parent->id)->orderBy('id')->get();

    expect($text)->toContain('Build auth')
        ->and($children->pluck('title')->all())->toBe(['Add login form', 'Wire Sanctum'])
        ->and($children->every(fn ($c) => $c->section_id === $backlog->id))->toBeTrue();
});

it('create_task inserts the new task at the top of the section', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $existing = Task::create(['project_id' => $project->id, 'section_id' => $backlog->id, 'title' => 'Old', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 21,
            'params' => ['name' => 'create_task', 'arguments' => ['title' => 'New top', 'section' => 'Backlog']],
        ])->assertStatus(200);

    $new = Task::where('title', 'New top')->first();
    expect($new->position)->toBe(0)
        ->and($existing->fresh()->position)->toBe(1);
});

it('create_task lands an unsectioned task in the Triage inbox when one exists', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $triage = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Triage', 'position' => 1]);

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 22,
            'params' => ['name' => 'create_task', 'arguments' => ['title' => 'Unsorted']],
        ])->assertStatus(200);

    expect(Task::where('title', 'Unsorted')->first()->section_id)->toBe($triage->id);
});

it('create_task response leads with the task name, not the id', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 23,
            'params' => ['name' => 'create_task', 'arguments' => ['title' => 'Named thing', 'section' => 'Backlog']],
        ])->json('result.content.0.text');

    expect($text)->toContain('"Named thing"')->and($text)->not->toContain('#');
});
