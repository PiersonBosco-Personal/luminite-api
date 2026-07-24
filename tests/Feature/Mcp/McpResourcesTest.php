<?php

use App\Models\Decision;
use App\Models\McpHistory;
use App\Models\ThreadEntry;

it('initialize advertises the resources capability', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
        ->assertStatus(200)
        ->assertJsonPath('result.capabilities.resources', []);
});

it('resources/list returns the thread and decisions resources', function () {
    [$raw] = mcpToken();

    $uris = collect($this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'resources/list', 'id' => 2])
        ->assertStatus(200)
        ->json('result.resources'))->pluck('uri');

    expect($uris)->toContain('luminite://thread')
        ->and($uris)->toContain('luminite://decisions');
});

it('resources/read luminite://thread returns the project stream', function () {
    [$raw, , $project] = mcpToken();
    ThreadEntry::factory()->create([
        'project_id' => $project->id,
        'type'       => 'gotcha',
        'content'    => 'watch the pgsql driver gate',
    ]);

    $response = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'resources/read', 'id' => 3,
            'params'  => ['uri' => 'luminite://thread'],
        ])
        ->assertStatus(200);

    expect($response->json('result.contents.0.uri'))->toBe('luminite://thread')
        ->and($response->json('result.contents.0.mimeType'))->toBe('text/markdown')
        ->and($response->json('result.contents.0.text'))->toContain('watch the pgsql driver gate');
});

it('resources/read luminite://decisions returns active decisions', function () {
    [$raw, , $project] = mcpToken();
    Decision::factory()->create([
        'project_id' => $project->id,
        'decision'   => 'Use Square as the payment processor',
        'status'     => 'active',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'resources/read', 'id' => 4,
            'params'  => ['uri' => 'luminite://decisions'],
        ])
        ->assertStatus(200)
        ->json('result.contents.0.text');

    expect($text)->toContain('Use Square as the payment processor');
});

it('resources/read returns -32602 for an unknown uri', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'resources/read', 'id' => 5,
            'params'  => ['uri' => 'luminite://nope'],
        ])
        ->assertStatus(200)
        ->assertJsonPath('error.code', -32602);
});

it('resources/read is denied to a token without the read scope', function () {
    [$raw] = mcpToken([], ['write']); // write-only, no read

    $error = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'resources/read', 'id' => 6,
            'params'  => ['uri' => 'luminite://thread'],
        ])
        ->assertStatus(200)
        ->json('error');

    expect($error['code'])->toBe(-32603)
        ->and($error['message'])->toContain('read');
});

it('resource methods write nothing to mcp_history', function () {
    [$raw, , $project] = mcpToken();
    ThreadEntry::factory()->create(['project_id' => $project->id]);

    $this->withToken($raw)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'resources/list', 'id' => 7]);
    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0', 'method' => 'resources/read', 'id' => 8,
        'params'  => ['uri' => 'luminite://thread'],
    ]);

    expect(McpHistory::count())->toBe(0);
});
