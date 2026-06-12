<?php

use App\Models\McpHistory;

it('initialize advertises the prompts capability', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
        ->assertStatus(200)
        ->assertJsonPath('result.capabilities.prompts', []);
});

it('prompts/list includes initialize-project', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'prompts/list', 'id' => 2])
        ->assertStatus(200)
        ->assertJsonPath('result.prompts.0.name', 'initialize-project');
});

it('prompts/get returns the orchestration messages', function () {
    [$raw] = mcpToken();

    $response = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'prompts/get',
            'id'      => 3,
            'params'  => ['name' => 'initialize-project'],
        ])
        ->assertStatus(200);

    $text = $response->json('result.messages.0.content.text');

    expect($response->json('result.messages.0.role'))->toBe('user')
        ->and($response->json('result.messages.0.content.type'))->toBe('text')
        ->and($text)->toContain('get_session_context')
        ->and($text)->toContain('initialize_project')
        ->and($text)->toContain('approval');
});

it('prompts/get returns -32602 for an unknown prompt', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'prompts/get',
            'id'      => 4,
            'params'  => ['name' => 'nope'],
        ])
        ->assertStatus(200)
        ->assertJsonPath('error.code', -32602);
});

it('lists and returns the triage-todos prompt', function () {
    [$raw] = mcpToken();

    $names = collect($this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'prompts/list', 'id' => 1])
        ->json('result.prompts'))->pluck('name');
    expect($names)->toContain('triage-todos');

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'prompts/get', 'id' => 2,
            'params'  => ['name' => 'triage-todos'],
        ])
        ->assertStatus(200)
        ->assertJsonPath('result.messages.0.role', 'user')
        ->assertJsonPath('result.messages.0.content.text', fn ($t) => str_contains($t, 'Triage'));
});

it('prompt methods write nothing to mcp_history', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'prompts/list', 'id' => 5]);
    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'prompts/get', 'id' => 6,
        'params'  => ['name' => 'initialize-project'],
    ]);

    expect(McpHistory::count())->toBe(0);
});
