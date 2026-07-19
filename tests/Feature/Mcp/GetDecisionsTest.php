<?php

use App\Models\Decision;

function getDecisionsCall($test, string $raw, array $arguments = [], int $id = 1): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => $id,
            'params'  => ['name' => 'get_decisions', 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('returns active decisions only by default', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    Decision::factory()->create(['project_id' => $project->id, 'decision' => 'Use Square', 'status' => 'active']);
    Decision::factory()->create(['project_id' => $project->id, 'decision' => 'Use Stripe', 'status' => 'superseded']);

    $text = getDecisionsCall($this, $raw);

    expect($text)->toContain('Use Square')
        ->and($text)->not->toContain('Use Stripe');
});

it('includes superseded decisions and their successor link when asked', function () {
    [$raw, , $project] = mcpToken([], ['read']);
    $new = Decision::factory()->create(['project_id' => $project->id, 'decision' => 'Use Square', 'status' => 'active']);
    Decision::factory()->create([
        'project_id'       => $project->id,
        'decision'         => 'Use Stripe',
        'status'           => 'superseded',
        'superseded_by_id' => $new->id,
    ]);

    $text = getDecisionsCall($this, $raw, ['include_superseded' => true]);

    expect($text)->toContain('Use Square')
        ->and($text)->toContain('Use Stripe')
        ->and($text)->toContain("superseded by #{$new->id}");
});

it('reports an empty state', function () {
    [$raw] = mcpToken([], ['read']);
    expect(getDecisionsCall($this, $raw))->toContain('No active decisions');
});
