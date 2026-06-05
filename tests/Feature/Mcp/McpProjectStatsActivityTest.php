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
