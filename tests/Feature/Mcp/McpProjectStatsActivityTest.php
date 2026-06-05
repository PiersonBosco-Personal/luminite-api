<?php

use App\Models\McpHistory;
use Laravel\Sanctum\Sanctum;

it('mcp activity endpoint returns mcp_history rows for the project', function () {
    [, , $project, $user] = mcpToken();

    McpHistory::create([
        'mcp_token_id' => null, 'user_id' => $user->id, 'project_id' => $project->id,
        'tool' => 'create_task', 'arguments' => ['title' => 'X'],
        'status' => 'success', 'duration_ms' => 12, 'result_summary' => 'Created task #1: X',
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$project->id}/mcp/activity")
        ->assertStatus(200)
        ->assertJsonPath('data.0.tool', 'create_task')
        ->assertJsonPath('data.0.status', 'success')
        ->assertJsonPath('data.0.result_summary', 'Created task #1: X')
        ->assertJsonPath('data.0.user.id', $user->id);
});

it('mcp stats endpoint aggregates mcp_history over the trailing week', function () {
    [, , $project, $user] = mcpToken();

    foreach (['get_open_tasks', 'get_open_tasks', 'complete_task'] as $i => $tool) {
        McpHistory::create([
            'mcp_token_id' => null, 'user_id' => $user->id, 'project_id' => $project->id,
            'tool' => $tool, 'arguments' => null,
            'status' => 'success', 'duration_ms' => ($i + 1) * 10, 'result_summary' => 'ok',
        ]);
    }
    McpHistory::create([
        'mcp_token_id' => null, 'user_id' => $user->id, 'project_id' => $project->id,
        'tool' => 'create_task', 'arguments' => null,
        'status' => 'error', 'duration_ms' => 5, 'error_message' => 'boom',
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$project->id}/mcp/stats")
        ->assertStatus(200)
        ->assertJsonPath('data.requests_this_week', 4)
        ->assertJsonPath('data.tasks_completed_this_week', 1)
        ->assertJsonPath('data.last_activity_user', $user->name);
});
