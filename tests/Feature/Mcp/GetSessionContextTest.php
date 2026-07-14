<?php

use App\Models\ActivityLog;
use App\Models\Label;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\TechStack;
use App\Models\ThreadEntry;

it('get_session_context returns project info', function () {
    [$raw] = mcpToken([
        'name'        => 'Dev Tracker',
        'description' => 'Track dev work',
        'goals'       => 'Ship v1',
        'status'      => 'active',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_session_context', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Dev Tracker')
        ->and($text)->toContain('Track dev work')
        ->and($text)->toContain('Ship v1');
});

it('get_session_context includes tech stack', function () {
    [$raw, , $project] = mcpToken(['name' => 'Stack Project']);

    $root = TechStack::factory()->create([
        'project_id' => $project->id,
        'name'       => 'React',
        'version'    => '18',
        'parent_id'  => null,
    ]);
    TechStack::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Vite',
        'version'    => '5',
        'parent_id'  => $root->id,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 2,
            'params'  => ['name' => 'get_session_context', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('React (18)')
        ->and($text)->toContain('Vite (5)');
});

it('get_session_context includes open and in_progress tasks', function () {
    [$raw, , $project] = mcpToken();
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Open task', 'status' => 'todo']);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Active task', 'status' => 'in_progress']);
    Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'title' => 'Done task', 'status' => 'done']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 3,
            'params'  => ['name' => 'get_session_context', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Open task')
        ->and($text)->toContain('Active task')
        ->and($text)->not->toContain('Done task');
});

it('get_session_context includes recent activity', function () {
    [$raw, , $project, $user] = mcpToken();

    ActivityLog::factory()->create([
        'project_id'   => $project->id,
        'user_id'      => $user->id,
        'event_type'   => 'task.completed',
        'subject_type' => 'task',
        'subject_label'=> 'Build API',
        'description'  => 'Pierson completed Build API',
        'via_mcp'      => false,
        'created_at'   => now()->subHours(2),
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 4,
            'params'  => ['name' => 'get_session_context', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Pierson completed Build API');
});

it('get_session_context is listed in tools/list', function () {
    [$raw] = mcpToken();

    $tools = $this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 5])
        ->assertStatus(200)
        ->json('result.tools');

    expect(collect($tools)->pluck('name'))->toContain('get_session_context');
});

it('get_session_context lists sections and labels with ids', function () {
    [$raw, , $project] = mcpToken();
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'In Progress', 'position' => 1]);
    Label::factory()->create(['project_id' => $project->id, 'name' => 'bug', 'color' => '#ef4444']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_session_context', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Sections:')
        ->and($text)->toContain('Backlog')
        ->and($text)->toContain('In Progress')
        ->and($text)->toContain('Labels:')
        ->and($text)->toContain('bug')
        ->and($text)->toContain('#ef4444');
});

function sessionContextText($test, string $raw): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_session_context', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('session context shows the project memory block when entries exist', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    ThreadEntry::factory()->create(['project_id' => $project->id, 'type' => 'gotcha', 'content' => 'Watch the coupling.']);

    expect(sessionContextText($this, $raw))
        ->toContain('Project Memory')
        ->toContain('Watch the coupling.');
});

it('session context surfaces decisions before other entries', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    // momentum is NEWER, decision is OLDER — decision must still render first.
    ThreadEntry::factory()->create(['project_id' => $project->id, 'type' => 'decision', 'content' => 'DECIDED', 'created_at' => now()->subHour()]);
    ThreadEntry::factory()->create(['project_id' => $project->id, 'type' => 'momentum', 'content' => 'MOMENTUM', 'created_at' => now()]);

    $text = sessionContextText($this, $raw);
    expect(strpos($text, 'DECIDED'))->toBeLessThan(strpos($text, 'MOMENTUM'));
});

it('session context omits the memory block gracefully when empty', function () {
    [$raw] = mcpToken([], ['read']);
    expect(sessionContextText($this, $raw))->toContain('Project Memory: none yet');
});

it('session context caps the memory block and shows an overflow footer', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    ThreadEntry::factory()->count(15)->create(['project_id' => $project->id, 'type' => 'momentum']);

    $text = sessionContextText($this, $raw);
    expect($text)->toContain('… +3 more'); // 15 total, cap 12
});
