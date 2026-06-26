<?php

use App\Models\Task;
use App\Models\TaskSection;

function updateTaskViaMcp($test, string $raw, array $args, int $id = 1): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => $id,
            'params' => ['name' => 'update_task', 'arguments' => $args],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('update_task creates subtasks under the target task in its section', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $task = Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'Parent', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $text = updateTaskViaMcp($this, $raw, [
        'task_id' => $task->id,
        'subtasks' => ['Step one', 'Step two'],
    ]);

    $children = Task::where('parent_task_id', $task->id)->orderBy('id')->get();
    expect($text)->toContain('subtasks')
        ->and($children->pluck('title')->all())->toBe(['Step one', 'Step two'])
        ->and($children->every(fn ($c) => $c->section_id === $section->id))->toBeTrue();
});

it('update_task treats subtasks-only as a real change (not "No changes")', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    $task = Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'P', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $text = updateTaskViaMcp($this, $raw, ['task_id' => $task->id, 'subtasks' => ['Only child']]);

    expect($text)->not->toContain('No changes');
    expect(Task::where('parent_task_id', $task->id)->count())->toBe(1);
});

it('update_task places subtasks in the destination section when also moving the task', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $done = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 1]);
    $task = Task::create([
        'project_id' => $project->id, 'section_id' => $backlog->id,
        'title' => 'Mover', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    updateTaskViaMcp($this, $raw, [
        'task_id' => $task->id,
        'section' => 'Done',
        'subtasks' => ['Child A'],
    ]);

    $child = Task::where('parent_task_id', $task->id)->first();
    expect($child->section_id)->toBe($done->id);
});

it('update_task with no fields still reports no changes', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    $task = Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'P', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $text = updateTaskViaMcp($this, $raw, ['task_id' => $task->id]);
    expect($text)->toContain('No changes');
});
