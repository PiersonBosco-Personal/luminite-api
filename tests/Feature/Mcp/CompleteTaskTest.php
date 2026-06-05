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
        'title'      => 'Ship feature',
        'status'     => 'todo',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'complete_task', 'arguments' => [
                'task_id' => $task->id,
                'notes'   => 'verified on staging',
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Completed task')
        ->and($task->fresh()->status)->toBe('done');

    $log = ActivityLog::where('subject_id', $task->id)->where('event_type', 'task.completed')->first();
    expect($log)->not->toBeNull()
        ->and($log->via_mcp)->toBeTrue()
        ->and($log->description)->toContain('verified on staging');
});

it('complete_task rejects a task from another project', function () {
    [$raw] = mcpToken([], ['read', 'write']);
    $otherSection = TaskSection::factory()->create(['position' => 0]);
    $foreign = Task::factory()->create(['section_id' => $otherSection->id, 'project_id' => $otherSection->project_id]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 2,
            'params'  => ['name' => 'complete_task', 'arguments' => ['task_id' => $foreign->id]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Error')
        ->and($foreign->fresh()->status)->not->toBe('done');
});
