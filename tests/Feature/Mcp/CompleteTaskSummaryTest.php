<?php

use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\TaskSection;

function completeSummaryCall($test, string $raw, array $arguments): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 1,
            'params' => ['name' => 'complete_task', 'arguments' => $arguments],
        ])->assertStatus(200)->json('result.content.0.text');
}

it('records a claude completion with summary and rationale', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 0]);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    completeSummaryCall($this, $raw, [
        'task_id'   => $task->id,
        'summary'   => 'Reworked the 401 path to show an inline error',
        'rationale' => 'The reload wiped the form and looked like a crash',
    ]);

    $completion = TaskCompletion::where('task_id', $task->id)->latest('id')->first();
    expect($completion)->not->toBeNull()
        ->and($completion->source)->toBe('claude')
        ->and($completion->summary_what)->toBe('Reworked the 401 path to show an inline error')
        ->and($completion->summary_why)->toBe('The reload wiped the form and looked like a crash');
});

it('still records a completion row when summary is omitted (fail-open)', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 0]);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    completeSummaryCall($this, $raw, ['task_id' => $task->id]);

    $completion = TaskCompletion::where('task_id', $task->id)->latest('id')->first();
    expect($completion)->not->toBeNull()
        ->and($completion->summary_what)->toBeNull()
        ->and($task->fresh()->status)->toBe('done');
});
