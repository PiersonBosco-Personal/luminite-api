<?php

use App\Models\ActivityLog;
use App\Models\McpHistory;

it('log_session_activity records to mcp_history only, not activity_logs', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'log_session_activity', 'arguments' => [
                'summary'         => 'Auth refactor',
                'files_changed'   => ['a.php', 'b.php', 'c.php'],
                'tasks_completed' => [44, 45],
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Auth refactor')
        ->and($text)->toContain('3 files')
        ->and($text)->toContain('2 tasks')
        ->and(ActivityLog::where('project_id', $project->id)->count())->toBe(0);

    $row = McpHistory::where('project_id', $project->id)->where('tool', 'log_session_activity')->first();
    expect($row)->not->toBeNull()
        ->and($row->arguments['summary'])->toBe('Auth refactor');
});

it('log_session_activity requires a summary', function () {
    [$raw] = mcpToken([], ['read', 'write']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 2,
            'params'  => ['name' => 'log_session_activity', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Error');
});
