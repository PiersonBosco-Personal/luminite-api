<?php

it('initialize instructions route decisions to add_thread_entry, not create_note', function () {
    [$raw] = mcpToken();

    $instructions = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1,
            'params'  => ['protocolVersion' => '2025-06-18'],
        ])
        ->assertStatus(200)
        ->json('result.instructions');

    expect($instructions)
        ->toContain('add_thread_entry')
        ->not->toContain('create_note');
});
