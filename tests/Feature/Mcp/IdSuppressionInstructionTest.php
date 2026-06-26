<?php

it('initialize response instructs never to surface numeric ids', function () {
    [$raw] = mcpToken();

    $instructions = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1,
            'params' => ['protocolVersion' => '2025-06-18'],
        ])
        ->assertStatus(200)
        ->json('result.instructions');

    expect($instructions)
        ->toContain('never')
        ->and(strtolower($instructions))->toContain('id')
        ->and(strtolower($instructions))->toContain('title');
});
