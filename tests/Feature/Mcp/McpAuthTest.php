<?php

use App\Models\McpToken;
use App\Models\User;

it('returns 401 JSON-RPC error when Authorization header is missing', function () {
    $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
         ->assertStatus(401)
         ->assertJsonPath('error.message', 'Unauthorized: missing token');
});

it('returns 401 JSON-RPC error when token is invalid', function () {
    $this->withToken('not-a-real-token')
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
         ->assertStatus(401)
         ->assertJsonPath('error.message', 'Unauthorized: invalid or expired token');
});

it('returns 401 JSON-RPC error when token is expired', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    [$token, $raw] = McpToken::generate($user, $project, 'test', ['read']);
    McpToken::where('token', hash('sha256', $raw))->update(['expires_at' => now()->subDay()]);

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
         ->assertStatus(401)
         ->assertJsonPath('error.message', 'Unauthorized: invalid or expired token');
});

it('accepts a valid token and passes request through', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
         ->assertStatus(200)
         ->assertJsonPath('result.serverInfo.name', 'luminite');
});

it('increments request_count on each authenticated request', function () {
    [$raw, $token] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1]);

    expect($token->fresh()->request_count)->toBe(1);
});

it('updates last_used_at on each authenticated request', function () {
    [$raw, $token] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1]);

    expect($token->fresh()->last_used_at)->not->toBeNull();
});
