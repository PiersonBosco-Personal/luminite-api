<?php

use App\Models\McpToken;
use App\Models\TechStack;
use App\Models\User;

it('returns server info on initialize', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
         ->assertStatus(200)
         ->assertJsonPath('jsonrpc', '2.0')
         ->assertJsonPath('result.serverInfo.name', 'luminite')
         ->assertJsonPath('result.protocolVersion', '2024-11-05');
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
