<?php

use App\Models\McpHistory;
use App\Models\Project;
use App\Models\User;

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
