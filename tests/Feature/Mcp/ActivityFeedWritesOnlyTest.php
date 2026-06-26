<?php

use App\Models\ActivityLog;
use App\Models\McpHistory;
use App\Models\TaskSection;

function callMcpTool($test, string $raw, string $name, array $args, int $id = 1)
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => $id,
            'params' => ['name' => $name, 'arguments' => $args],
        ])->assertStatus(200);
}

it('read tools record mcp_history but never an activity-feed row', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    $reads = [
        ['get_session_context', []],
        ['get_open_tasks', []],
        ['get_sections', []],
        ['get_labels', []],
        ['get_recent_activity', []],
        ['get_project_notes', []],
    ];

    foreach ($reads as $i => [$name, $args]) {
        callMcpTool($this, $raw, $name, $args, $i + 1);
    }

    expect(McpHistory::where('project_id', $project->id)->count())->toBe(count($reads));
    expect(ActivityLog::where('project_id', $project->id)->count())->toBe(0);
});

it('a write tool records BOTH mcp_history and an activity-feed row', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);

    callMcpTool($this, $raw, 'create_task', ['title' => 'A task', 'section' => 'Backlog']);

    expect(McpHistory::where('project_id', $project->id)->where('tool', 'create_task')->count())->toBe(1);
    expect(ActivityLog::where('project_id', $project->id)->where('event_type', 'task.created')->count())->toBe(1);
});
