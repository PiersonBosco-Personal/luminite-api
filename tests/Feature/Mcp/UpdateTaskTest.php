<?php

use App\Models\Task;
use App\Models\TaskSection;

function seedBoard($project): array
{
    return [
        'todo' => TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'To Do', 'position' => 0]),
        'inprog' => TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'In Progress', 'position' => 1]),
        'done' => TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 2]),
    ];
}

it('status in_progress pulls the task into the In Progress section (Rule B)', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $s = seedBoard($project);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $s['todo']->id, 'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);

    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 30,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'status' => 'in_progress']],
    ])->assertStatus(200);

    $task->refresh();
    expect($task->section_id)->toBe($s['inprog']->id)
        ->and($task->status)->toBe('in_progress')
        ->and($task->position)->toBe(0);
});

it('moving into Done sets status done (Rule A)', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $s = seedBoard($project);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $s['todo']->id, 'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);

    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 31,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'section' => 'Done']],
    ])->assertStatus(200);

    expect($task->refresh()->status)->toBe('done');
});

it('moving out of Done reverts status based on destination (Rule A)', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $s = seedBoard($project);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $s['done']->id, 'title' => 'T', 'status' => 'done', 'priority' => 'medium', 'position' => 0]);

    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 32,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'section' => 'To Do']],
    ])->assertStatus(200);

    expect($task->refresh()->status)->toBe('todo');
});

it('a bare status done with no move is a no-op for status (Rule C)', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $s = seedBoard($project);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $s['todo']->id, 'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);

    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 33,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'status' => 'done', 'priority' => 'high']],
    ])->assertStatus(200);

    $task->refresh();
    expect($task->status)->toBe('todo')->and($task->priority)->toBe('high');
});

it('a section move inserts at the top of the destination', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $s = seedBoard($project);
    $occupant = Task::create(['project_id' => $project->id, 'section_id' => $s['inprog']->id, 'title' => 'Occupant', 'status' => 'in_progress', 'priority' => 'medium', 'position' => 0]);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $s['todo']->id, 'title' => 'Mover', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);

    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 34,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'section' => 'In Progress']],
    ])->assertStatus(200);

    expect($task->refresh()->position)->toBe(0)
        ->and($occupant->fresh()->position)->toBe(1);
});

it('a section move wins over a conflicting status arg (Rule A precedence)', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $s = seedBoard($project);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $s['inprog']->id, 'title' => 'T', 'status' => 'in_progress', 'priority' => 'medium', 'position' => 0]);

    // Caller asks for status=done but moves the task to To Do — the section wins.
    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 36,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'section' => 'To Do', 'status' => 'done']],
    ])->assertStatus(200);

    $task->refresh();
    expect($task->section_id)->toBe($s['todo']->id)
        ->and($task->status)->toBe('todo');
});

it('status in_progress without an In Progress section updates status with no move (Rule B fallback)', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $todo = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'To Do', 'position' => 0]);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $todo->id, 'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);

    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 37,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'status' => 'in_progress']],
    ])->assertStatus(200);

    $task->refresh();
    expect($task->section_id)->toBe($todo->id)
        ->and($task->status)->toBe('in_progress');
});

it('update_task response leads with the task name, not the id', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $s = seedBoard($project);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $s['todo']->id, 'title' => 'Rename me', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);

    $text = $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 35,
        'params' => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'priority' => 'high']],
    ])->json('result.content.0.text');

    expect($text)->toContain('"Rename me"')->and($text)->not->toContain('#');
});
