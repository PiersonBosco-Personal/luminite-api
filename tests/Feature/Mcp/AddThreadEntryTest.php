<?php

use App\Models\ActivityLog;
use App\Models\McpHistory;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\ThreadEntry;

it('persists a thread entry with all columns', function () {
    $entry = ThreadEntry::factory()->create([
        'type'    => 'decision',
        'content' => 'Chose project-scoped stream over task-scoped.',
        'trigger' => 'manual',
    ]);

    expect($entry->fresh())
        ->type->toBe('decision')
        ->content->toBe('Chose project-scoped stream over task-scoped.')
        ->trigger->toBe('manual')
        ->and($entry->project_id)->not->toBeNull()
        ->and($entry->created_by)->not->toBeNull();

    expect(ThreadEntry::TYPES)->toBe(['momentum', 'decision', 'dead_end', 'gotcha']);
});

function addThreadEntryCall($test, string $raw, array $arguments, int $id = 1): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => $id,
            'params'  => ['name' => 'add_thread_entry', 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('add_thread_entry appends an entry and writes NO activity log', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $text = addThreadEntryCall($this, $raw, [
        'type'    => 'decision',
        'content' => 'Switched payment processor to Square.',
    ]);

    expect($text)->toContain('decision');

    $entry = ThreadEntry::where('project_id', $project->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->type)->toBe('decision')
        ->and($entry->trigger)->toBe('manual')
        ->and($entry->task_id)->toBeNull();

    expect(ActivityLog::where('project_id', $project->id)->count())->toBe(0);
    expect(McpHistory::where('project_id', $project->id)->where('tool', 'add_thread_entry')->where('status', 'success')->exists())->toBeTrue();
});

it('add_thread_entry breadcrumbs a task in the same project', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    $task = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    addThreadEntryCall($this, $raw, ['type' => 'momentum', 'content' => 'Half-done.', 'task_id' => $task->id]);

    expect(ThreadEntry::where('project_id', $project->id)->first()->task_id)->toBe($task->id);
});

it('add_thread_entry rejects an unknown type without writing a row', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $text = addThreadEntryCall($this, $raw, ['type' => 'banana', 'content' => 'nope']);

    expect($text)->toContain('Error')
        ->and($text)->toContain('momentum')
        ->and(ThreadEntry::where('project_id', $project->id)->count())->toBe(0);
});

it('add_thread_entry rejects a task_id from another project', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $foreignSection = TaskSection::factory()->create(['position' => 0]);
    $foreignTask = Task::factory()->create(['section_id' => $foreignSection->id, 'project_id' => $foreignSection->project_id]);

    $text = addThreadEntryCall($this, $raw, ['type' => 'gotcha', 'content' => 'x', 'task_id' => $foreignTask->id]);

    expect($text)->toContain('Error')
        ->and(ThreadEntry::where('project_id', $project->id)->count())->toBe(0);
});

it('add_thread_entry requires the write scope', function () {
    [$raw, , $project] = mcpToken(); // read-only

    $error = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 9,
            'params'  => ['name' => 'add_thread_entry', 'arguments' => ['type' => 'decision', 'content' => 'blocked']],
        ])
        ->assertStatus(200)
        ->json('error.message');

    expect($error)->toContain('write')
        ->and(ThreadEntry::where('project_id', $project->id)->count())->toBe(0);
});

it('add_thread_entry stores an explicit commit trigger', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    addThreadEntryCall($this, $raw, [
        'type'    => 'momentum',
        'content' => 'feat(api): add task reordering endpoint',
        'trigger' => 'commit',
    ]);

    expect(ThreadEntry::where('project_id', $project->id)->first()->trigger)->toBe('commit');
});

it('add_thread_entry rejects an unknown trigger without writing a row', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $text = addThreadEntryCall($this, $raw, [
        'type'    => 'momentum',
        'content' => 'x',
        'trigger' => 'banana',
    ]);

    expect($text)->toContain('Error')
        ->and(ThreadEntry::where('project_id', $project->id)->count())->toBe(0);
});
