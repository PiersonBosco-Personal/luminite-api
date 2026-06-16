<?php

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;

it('get_open_tasks returns todo and in_progress tasks by default', function () {
    [$raw, , $project] = mcpToken();
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Open task', 'status' => 'todo']);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Active task', 'status' => 'in_progress']);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Finished task', 'status' => 'done']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => ['name' => 'get_open_tasks', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Open task')
        ->and($text)->toContain('Active task')
        ->and($text)->not->toContain('Finished task');
});

it('get_open_tasks filters by status', function () {
    [$raw, , $project] = mcpToken();
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Blocked task', 'status' => 'blocked']);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Todo task', 'status' => 'todo']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 2,
            'params' => ['name' => 'get_open_tasks', 'arguments' => ['status' => 'blocked']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Blocked task')
        ->and($text)->not->toContain('Todo task');
});

it('get_open_tasks filters by priority', function () {
    [$raw, , $project] = mcpToken();
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Urgent task', 'status' => 'todo', 'priority' => 'urgent']);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Low task', 'status' => 'todo', 'priority' => 'low']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 3,
            'params' => ['name' => 'get_open_tasks', 'arguments' => ['priority' => 'urgent']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Urgent task')
        ->and($text)->not->toContain('Low task');
});

it('get_open_tasks filters by section_id', function () {
    [$raw, , $project] = mcpToken();
    $sectionA = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Sprint 1', 'position' => 0]);
    $sectionB = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Sprint 2', 'position' => 1]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $sectionA->id, 'title' => 'Sprint 1 task', 'status' => 'todo']);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $sectionB->id, 'title' => 'Sprint 2 task', 'status' => 'todo']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 4,
            'params' => ['name' => 'get_open_tasks', 'arguments' => ['section_id' => $sectionA->id]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Sprint 1 task')
        ->and($text)->not->toContain('Sprint 2 task');
});

it('get_open_tasks filters by label_id', function () {
    [$raw, , $project] = mcpToken();
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'bug']);

    $tagged = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Tagged task', 'status' => 'todo']);
    $untagged = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Untagged task', 'status' => 'todo']);

    $tagged->labels()->attach($label->id);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 5,
            'params' => ['name' => 'get_open_tasks', 'arguments' => ['label_id' => $label->id]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Tagged task')
        ->and($text)->not->toContain('Untagged task');
});

it('get_open_tasks does not return tasks from other projects', function () {
    [$raw] = mcpToken();

    $other = createProject(User::factory()->create());
    $section = TaskSection::factory()->create(['project_id' => $other->id, 'position' => 0]);
    Task::factory()->create(['project_id' => $other->id, 'section_id' => $section->id, 'title' => 'Other project task', 'status' => 'todo']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 6,
            'params' => ['name' => 'get_open_tasks', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('Other project task');
});

it('get_open_tasks returns no-tasks message when none match', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 7,
            'params' => ['name' => 'get_open_tasks', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No tasks');
});

it('get_open_tasks includes a truncated description and nested subtasks', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'To Do', 'position' => 0]);
    $parent = Task::create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Parent task', 'description' => 'Some helpful detail.', 'status' => 'todo', 'priority' => 'high', 'position' => 0]);
    Task::create(['project_id' => $project->id, 'section_id' => $section->id, 'parent_task_id' => $parent->id, 'title' => 'Child A', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);
    Task::create(['project_id' => $project->id, 'section_id' => $section->id, 'parent_task_id' => $parent->id, 'title' => 'Child B', 'status' => 'done', 'priority' => 'medium', 'position' => 0]);

    $text = $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 50,
        'params' => ['name' => 'get_open_tasks', 'arguments' => []],
    ])->json('result.content.0.text');

    expect($text)->toContain('Some helpful detail.')
        ->and($text)->toContain('Child A — todo')
        ->and($text)->toContain('Child B — done')
        ->and($text)->toContain("#{$parent->id}"); // id retained for action
});
