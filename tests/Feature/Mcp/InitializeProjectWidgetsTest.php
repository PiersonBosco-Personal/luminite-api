<?php

use App\Models\Widget;

beforeEach(function () {
    $this->seed(\Database\Seeders\WidgetSeeder::class);
});

function initProjectViaMcp($test, string $raw, array $args, int $id = 1): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => $id,
            'params' => ['name' => 'initialize_project', 'arguments' => $args],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('initialize_project rejects an unavailable (stub) widget slug', function () {
    [$raw, , $project] = mcpToken(['description' => null], ['read', 'write']);

    $text = initProjectViaMcp($this, $raw, [
        'details' => ['description' => 'A project'],
        'widgets' => ['ai_chat'], // active but is_available = false
    ]);

    expect($text)->toContain('Error')
        ->and($text)->toContain('widgets[0]');
    expect($project->fresh()->description)->toBeNull(); // nothing written
});

it('initialize_project accepts a built widget slug', function () {
    [$raw, , $project] = mcpToken(['description' => null], ['read', 'write']);

    $text = initProjectViaMcp($this, $raw, [
        'details' => ['description' => 'A project'],
        'widgets' => ['tasks_board'],
    ]);

    expect($text)->toContain('Initialized project');
    expect($project->fresh()->description)->toBe('A project');
});
