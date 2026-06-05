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
