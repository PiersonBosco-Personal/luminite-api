<?php

use App\Events\McpActivityCreated;
use App\Models\McpHistory;
use App\Models\McpToken;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('persists an mcp_history row with arguments cast to array and no updated_at', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    $row = McpHistory::create([
        'mcp_token_id'   => null,
        'user_id'        => $user->id,
        'project_id'     => $project->id,
        'tool'           => 'get_open_tasks',
        'arguments'      => ['status' => 'blocked'],
        'status'         => 'success',
        'duration_ms'    => 42,
        'result_summary' => 'Tasks (3):',
        'error_message'  => null,
    ]);

    expect($row->fresh()->arguments)->toBe(['status' => 'blocked'])
        ->and($row->status)->toBe('success')
        ->and($row->created_at)->not->toBeNull()
        ->and($row->updated_at)->toBeNull();
});

it('writes an mcp_history row when any tool is called', function () {
    [$raw, $token, $project] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_open_tasks', 'arguments' => []],
        ])
        ->assertStatus(200);

    $row = McpHistory::where('project_id', $project->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->tool)->toBe('get_open_tasks')
        ->and($row->status)->toBe('success')
        ->and($row->mcp_token_id)->toBe($token->id)
        ->and($row->user_id)->toBe($token->user_id)
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($row->result_summary)->not->toBeNull();
});

it('broadcasts McpActivityCreated on the project channel when a tool is called', function () {
    Event::fake([McpActivityCreated::class]);

    [$raw, , $project] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_open_tasks', 'arguments' => []],
        ])
        ->assertStatus(200);

    Event::assertDispatched(
        McpActivityCreated::class,
        fn (McpActivityCreated $e) => $e->projectId === $project->id
            && $e->history->tool === 'get_open_tasks'
            && $e->broadcastAs() === 'mcp.activity.created',
    );
});

it('writes an mcp_history error row when an unknown tool is called', function () {
    [$raw, , $project] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 9,
            'params'  => ['name' => 'does_not_exist', 'arguments' => []],
        ])
        ->assertStatus(200);

    $row = McpHistory::where('project_id', $project->id)->where('tool', 'does_not_exist')->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('error')
        ->and($row->error_message)->toContain('not found');
});
