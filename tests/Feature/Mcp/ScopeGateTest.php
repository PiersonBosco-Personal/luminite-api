<?php

use App\Models\McpHistory;
use App\Models\TaskSection;

it('rejects a write tool when the token lacks the write scope', function () {
    [$raw, , $project] = mcpToken(); // default ['read'] only
    TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    $error = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'create_task', 'arguments' => ['title' => 'Blocked']],
        ])
        ->assertStatus(200)
        ->json('error.message');

    expect($error)->toContain('write')
        ->and(\App\Models\Task::where('title', 'Blocked')->exists())->toBeFalse();

    $row = McpHistory::where('project_id', $project->id)->where('tool', 'create_task')->first();
    expect($row->status)->toBe('error')
        ->and($row->error_message)->toContain('write');
});
