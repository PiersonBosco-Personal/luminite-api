<?php

use App\Models\ActivityLog;
use App\Models\User;

it('get_recent_activity returns activity from the last 48 hours by default', function () {
    [$raw, , $project, $user] = mcpToken();

    ActivityLog::factory()->create([
        'project_id'   => $project->id,
        'user_id'      => $user->id,
        'event_type'   => 'task.created',
        'subject_type' => 'task',
        'subject_label'=> 'Build login',
        'description'  => 'Pierson created Build login',
        'via_mcp'      => false,
        'created_at'   => now()->subHours(1),
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Build login');
});

it('get_recent_activity excludes entries older than the since parameter', function () {
    [$raw, , $project, $user] = mcpToken();

    ActivityLog::factory()->create([
        'project_id'   => $project->id,
        'user_id'      => $user->id,
        'event_type'   => 'task.created',
        'subject_type' => 'task',
        'subject_label'=> 'Old task',
        'description'  => 'Pierson created Old task',
        'via_mcp'      => false,
        'created_at'   => now()->subDays(5),
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 2,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => ['since' => now()->subDays(3)->toIso8601String()]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('Old task');
});

it('get_recent_activity respects the limit parameter', function () {
    [$raw, , $project, $user] = mcpToken();

    foreach (range(1, 5) as $i) {
        ActivityLog::factory()->create([
            'project_id'   => $project->id,
            'user_id'      => $user->id,
            'event_type'   => 'task.created',
            'subject_type' => 'task',
            'subject_label'=> "Task {$i}",
            'description'  => "Created Task {$i}",
            'via_mcp'      => false,
            'created_at'   => now()->subMinutes($i),
        ]);
    }

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 3,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => ['limit' => 2]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    // Only 2 entries returned — count line occurrences
    expect(substr_count($text, 'Created Task'))->toBe(2);
});

it('get_recent_activity caps limit at 50', function () {
    [$raw, , $project, $user] = mcpToken();

    foreach (range(1, 55) as $i) {
        ActivityLog::factory()->create([
            'project_id'   => $project->id,
            'user_id'      => $user->id,
            'event_type'   => 'task.created',
            'subject_type' => 'task',
            'subject_label'=> "Task {$i}",
            'description'  => "Created Task {$i}",
            'via_mcp'      => false,
            'created_at'   => now()->subMinutes($i),
        ]);
    }

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 4,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => ['limit' => 100]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect(substr_count($text, 'Created Task'))->toBe(50);
});

it('get_recent_activity does not return activity from other projects', function () {
    [$raw] = mcpToken();

    $otherUser = User::factory()->create();
    $other = createProject($otherUser);
    ActivityLog::factory()->create([
        'project_id'   => $other->id,
        'user_id'      => $otherUser->id,
        'event_type'   => 'task.created',
        'subject_type' => 'task',
        'subject_label'=> 'Other project activity',
        'description'  => 'Created Other project activity',
        'via_mcp'      => false,
        'created_at'   => now()->subMinutes(10),
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 5,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('Other project activity');
});

it('get_recent_activity returns no-activity message when none exists', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 6,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No activity');
});
