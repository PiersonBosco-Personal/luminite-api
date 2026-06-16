<?php

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskSection;

it('complete_task marks a task done and logs notes via mcp', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'section_id' => $section->id,
        'title' => 'Ship feature',
        'status' => 'todo',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => ['name' => 'complete_task', 'arguments' => [
                'task_id' => $task->id,
                'notes' => 'verified on staging',
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('"'.$task->title.'"')
        ->and($task->fresh()->status)->toBe('done');

    $log = ActivityLog::where('subject_id', $task->id)->where('event_type', 'task.completed')->first();
    expect($log)->not->toBeNull()
        ->and($log->via_mcp)->toBeTrue()
        ->and($log->description)->toContain('verified on staging');
});

function completeTaskCall($test, string $raw, array $arguments): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => ['name' => 'complete_task', 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('complete_task moves the task to the Done section by default', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $done = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 1]);
    $settled = Task::factory()->create(['project_id' => $project->id, 'section_id' => $done->id, 'position' => 0]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'section_id' => $backlog->id,
        'status' => 'todo',
        'position' => 0,
    ]);

    $text = completeTaskCall($this, $raw, ['task_id' => $task->id]);

    expect($text)->toContain('"'.$task->title.'"')
        ->and($task->fresh()->status)->toBe('done')
        ->and($task->fresh()->section_id)->toBe($done->id)
        ->and($task->fresh()->position)->toBe(0)
        ->and($settled->fresh()->position)->toBe(1);
});

it('complete_task matches the Done section case-insensitively', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $done = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'DONE', 'position' => 1]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $backlog->id, 'status' => 'todo']);

    completeTaskCall($this, $raw, ['task_id' => $task->id]);

    expect($task->fresh()->section_id)->toBe($done->id);
});

it('complete_task moves the task to an explicit section by name', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $shipped = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Shipped', 'position' => 1]);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 2]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $backlog->id, 'status' => 'todo']);

    $text = completeTaskCall($this, $raw, ['task_id' => $task->id, 'section' => 'shipped']);

    expect($text)->toContain('"'.$task->title.'"')
        ->and($task->fresh()->status)->toBe('done')
        ->and($task->fresh()->section_id)->toBe($shipped->id);
});

it('complete_task moves the task to an explicit section by id', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $archive = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Archive', 'position' => 1]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $backlog->id, 'status' => 'todo']);

    completeTaskCall($this, $raw, ['task_id' => $task->id, 'section' => $archive->id]);

    expect($task->fresh()->section_id)->toBe($archive->id)
        ->and($task->fresh()->status)->toBe('done');
});

it('complete_task still completes and asks where to move when no Done section exists', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $progress = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'In Progress', 'position' => 1]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $backlog->id, 'status' => 'todo']);

    $text = completeTaskCall($this, $raw, ['task_id' => $task->id]);

    expect($text)->toContain('"'.$task->title.'"')
        ->and($text)->toContain('no "Done" section')
        ->and($text)->toContain('Ask the user')
        ->and($text)->toContain("[{$backlog->id}] Backlog")
        ->and($text)->toContain("[{$progress->id}] In Progress")
        ->and($task->fresh()->status)->toBe('done')
        ->and($task->fresh()->section_id)->toBe($backlog->id);
});

it('complete_task rejects an unknown section without changing the task', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $backlog->id, 'status' => 'todo']);

    $text = completeTaskCall($this, $raw, ['task_id' => $task->id, 'section' => 'Nonexistent']);

    expect($text)->toContain('Error')
        ->and($text)->toContain("[{$backlog->id}] Backlog")
        ->and($task->fresh()->status)->toBe('todo')
        ->and($task->fresh()->section_id)->toBe($backlog->id);
});

it('complete_task rejects a section id from another project', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $foreign = TaskSection::factory()->create(['name' => 'Done', 'position' => 0]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $backlog->id, 'status' => 'todo']);

    $text = completeTaskCall($this, $raw, ['task_id' => $task->id, 'section' => $foreign->id]);

    expect($text)->toContain('Error')
        ->and($task->fresh()->status)->toBe('todo')
        ->and($task->fresh()->section_id)->toBe($backlog->id);
});

it('complete_task moves an already-done task on a follow-up call', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $backlog = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    $shipped = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Shipped', 'position' => 1]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $backlog->id, 'status' => 'done']);

    $text = completeTaskCall($this, $raw, ['task_id' => $task->id, 'section' => 'Shipped']);

    expect($text)->toContain('"'.$task->title.'"')
        ->and($task->fresh()->section_id)->toBe($shipped->id);
});

it('complete_task rejects a task from another project', function () {
    [$raw] = mcpToken([], ['read', 'write']);
    $otherSection = TaskSection::factory()->create(['position' => 0]);
    $foreign = Task::factory()->create(['section_id' => $otherSection->id, 'project_id' => $otherSection->project_id]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 2,
            'params' => ['name' => 'complete_task', 'arguments' => ['task_id' => $foreign->id]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Error')
        ->and($foreign->fresh()->status)->not->toBe('done');
});

it('complete_task moves the task to the top of Done', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $todo = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'To Do', 'position' => 0]);
    $done = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 1]);
    $already = Task::create(['project_id' => $project->id, 'section_id' => $done->id, 'title' => 'Already done', 'status' => 'done', 'priority' => 'medium', 'position' => 0]);
    $task = Task::create(['project_id' => $project->id, 'section_id' => $todo->id, 'title' => 'Finish me', 'status' => 'in_progress', 'priority' => 'medium', 'position' => 0]);

    $text = $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 40,
        'params' => ['name' => 'complete_task', 'arguments' => ['task_id' => $task->id]],
    ])->json('result.content.0.text');

    $task->refresh();
    expect($task->status)->toBe('done')
        ->and($task->section_id)->toBe($done->id)
        ->and($task->position)->toBe(0)
        ->and($already->fresh()->position)->toBe(1)
        ->and($text)->toContain('"Finish me"')
        ->and($text)->not->toContain('#');
});
