<?php

use App\Models\McpToken;
use App\Models\TechStack;
use App\Models\User;

it('echoes the client-requested protocol version on initialize', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'initialize',
             'id'      => 1,
             'params'  => ['protocolVersion' => '2025-06-18'],
         ])
         ->assertStatus(200)
         ->assertJsonPath('jsonrpc', '2.0')
         ->assertJsonPath('result.serverInfo.name', 'luminite')
         ->assertJsonPath('result.protocolVersion', '2025-06-18');
});

it('falls back to the default protocol version when the client sends none', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
         ->assertStatus(200)
         ->assertJsonPath('jsonrpc', '2.0')
         ->assertJsonPath('result.protocolVersion', '2025-06-18');
});

it('lists available tools including get_project_context', function () {
    [$raw] = mcpToken();

    $data = $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2])
         ->assertStatus(200)
         ->json('result.tools');

    expect(collect($data)->pluck('name'))->toContain('get_project_context');
});

it('returns project name, description, and status in context', function () {
    [$raw] = mcpToken([
        'name'        => 'My App',
        'description' => 'A web platform',
        'status'      => 'active',
    ]);

    $text = $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'tools/call',
             'id'      => 3,
             'params'  => ['name' => 'get_project_context', 'arguments' => []],
         ])
         ->assertStatus(200)
         ->json('result.content.0.text');

    expect($text)->toContain('My App')
        ->and($text)->toContain('A web platform')
        ->and($text)->toContain('active');
});

it('returns tech stack entries in context', function () {
    $user    = User::factory()->create();
    $project = createProject($user, ['name' => 'Stack Test']);
    $root    = TechStack::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Laravel',
        'version'    => '11',
        'parent_id'  => null,
    ]);
    TechStack::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Sanctum',
        'version'    => null,
        'parent_id'  => $root->id,
    ]);
    [, $raw] = McpToken::generate($user, $project, 'test', ['read']);

    $text = $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'tools/call',
             'id'      => 4,
             'params'  => ['name' => 'get_project_context', 'arguments' => []],
         ])
         ->assertStatus(200)
         ->json('result.content.0.text');

    expect($text)->toContain('Laravel (11)')
        ->and($text)->toContain('Sanctum');
});

it('returns error for unknown method', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'unknown/method', 'id' => 5])
         ->assertStatus(200)
         ->assertJsonPath('error.code', -32601);
});

it('returns error for unknown tool name in tools/call', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'tools/call',
             'id'      => 6,
             'params'  => ['name' => 'nonexistent_tool', 'arguments' => []],
         ])
         ->assertStatus(200)
         ->assertJsonPath('error.code', -32601);
});

it('get_labels returns label names for the project', function () {
    [$raw, , $project] = mcpToken();

    \App\Models\Label::factory()->create([
        'project_id' => $project->id,
        'name'       => 'frontend',
        'color'      => '#3b82f6',
    ]);
    \App\Models\Label::factory()->create([
        'project_id' => $project->id,
        'name'       => 'bug',
        'color'      => '#ef4444',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 10,
            'params'  => ['name' => 'get_labels', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('frontend')
        ->and($text)->toContain('bug');
});

it('get_labels returns no-labels message when project has none', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 11,
            'params'  => ['name' => 'get_labels', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No labels');
});

it('get_labels does not return labels from other projects', function () {
    [$raw] = mcpToken();

    $otherUser    = \App\Models\User::factory()->create();
    $otherProject = createProject($otherUser);
    \App\Models\Label::factory()->create([
        'project_id' => $otherProject->id,
        'name'       => 'secret-label',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 12,
            'params'  => ['name' => 'get_labels', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('secret-label');
});

it('get_sections returns section names in order', function () {
    [$raw, , $project] = mcpToken();

    \App\Models\TaskSection::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Backlog',
        'position'   => 0,
    ]);
    \App\Models\TaskSection::factory()->create([
        'project_id' => $project->id,
        'name'       => 'In Progress',
        'position'   => 1,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 20,
            'params'  => ['name' => 'get_sections', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Backlog')
        ->and($text)->toContain('In Progress');
});

it('get_sections returns no-sections message when project has none', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 21,
            'params'  => ['name' => 'get_sections', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No sections');
});

it('get_sections does not return sections from other projects', function () {
    [$raw] = mcpToken();

    $other = createProject(\App\Models\User::factory()->create());
    \App\Models\TaskSection::factory()->create([
        'project_id' => $other->id,
        'name'       => 'secret-section',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 22,
            'params'  => ['name' => 'get_sections', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('secret-section');
});
