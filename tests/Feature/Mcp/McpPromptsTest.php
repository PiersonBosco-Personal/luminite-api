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

it('lists and returns the wrap-up prompt', function () {
    [$raw] = mcpToken();

    $names = collect($this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'prompts/list', 'id' => 1])
        ->json('result.prompts'))->pluck('name');
    expect($names)->toContain('wrap-up');

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'prompts/get', 'id' => 2,
            'params'  => ['name' => 'wrap-up'],
        ])
        ->assertStatus(200)
        ->assertJsonPath('result.messages.0.content.text', fn ($t) => str_contains($t, 'log_session_activity'));
});

it('wrap-up prompt captures memory via add_thread_entry, not create_note', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'prompts/get', 'id' => 7,
            'params'  => ['name' => 'wrap-up'],
        ])
        ->assertStatus(200)
        ->json('result.messages.0.content.text');

    expect($text)
        ->toContain('add_thread_entry')
        ->not->toContain('create_note');
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
