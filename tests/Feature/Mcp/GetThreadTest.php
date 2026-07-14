<?php

use App\Models\ActivityLog;
use App\Models\ThreadEntry;

function getThreadCall($test, string $raw, array $arguments = [], int $id = 1): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => $id,
            'params'  => ['name' => 'get_thread', 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('get_thread returns entries newest-first and writes no activity log', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    ThreadEntry::factory()->create(['project_id' => $project->id, 'content' => 'older', 'created_at' => now()->subHour()]);
    ThreadEntry::factory()->create(['project_id' => $project->id, 'content' => 'newer', 'created_at' => now()]);

    $text = getThreadCall($this, $raw);

    expect($text)->toContain('newer')->toContain('older');
    expect(strpos($text, 'newer'))->toBeLessThan(strpos($text, 'older'));
    expect(ActivityLog::where('project_id', $project->id)->count())->toBe(0);
});

it('get_thread reports an empty stream', function () {
    [$raw] = mcpToken([], ['read']);
    expect(getThreadCall($this, $raw))->toContain('empty');
});

it('get_thread caps limit at 50', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    ThreadEntry::factory()->count(3)->create(['project_id' => $project->id]);

    $text = getThreadCall($this, $raw, ['limit' => 999]);
    expect(substr_count($text, "\n- "))->toBe(3);
});

it('get_thread filters by type', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    ThreadEntry::factory()->create(['project_id' => $project->id, 'type' => 'decision', 'content' => 'D']);
    ThreadEntry::factory()->create(['project_id' => $project->id, 'type' => 'gotcha', 'content' => 'G']);

    $text = getThreadCall($this, $raw, ['type' => 'decision']);
    expect($text)->toContain('D')->not->toContain('G');
});

it('get_thread only returns the calling project\'s entries', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    ThreadEntry::factory()->create(['project_id' => $project->id, 'content' => 'mine']);
    ThreadEntry::factory()->create(['content' => 'theirs']); // different project

    expect(getThreadCall($this, $raw))->toContain('mine')->not->toContain('theirs');
});
