<?php

it('rejects an MCP token whose project is trashed', function () {
    [$raw, , $project] = mcpToken();

    $project->delete();

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => []],
        ])
        ->assertStatus(401)
        ->assertJsonPath('error.code', -32001);
});

it('accepts the same MCP token again after the project is restored', function () {
    [$raw, , $project] = mcpToken();

    $project->delete();
    $project->restore();

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_recent_activity', 'arguments' => []],
        ])
        ->assertStatus(200);
});
