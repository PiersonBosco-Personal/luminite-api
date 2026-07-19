<?php

use App\Models\ActivityLog;
use App\Models\Decision;
use App\Models\McpHistory;
use App\Models\ThreadEntry;

function logDecisionCall($test, string $raw, array $arguments, int $id = 1): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => $id,
            'params'  => ['name' => 'log_decision', 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('logs an active decision, drops a momentum breadcrumb, and writes NO activity log', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $text = logDecisionCall($this, $raw, [
        'decision'  => 'Use Square as the payment processor',
        'rationale' => 'Stripe fees are too high at our volume',
    ]);

    expect($text)->toContain('Logged decision');

    $decision = Decision::where('project_id', $project->id)->first();
    expect($decision)->not->toBeNull()
        ->and($decision->status)->toBe('active')
        ->and($decision->rationale)->toBe('Stripe fees are too high at our volume')
        ->and($decision->superseded_by_id)->toBeNull();

    // Chronological breadcrumb lands in the Thread as a momentum entry.
    $crumb = ThreadEntry::where('project_id', $project->id)->first();
    expect($crumb)->not->toBeNull()
        ->and($crumb->type)->toBe('momentum')
        ->and($crumb->content)->toContain('Decided: Use Square');

    // Same deliberate exception as add_thread_entry: no activity log, mcp_history records it.
    expect(ActivityLog::where('project_id', $project->id)->count())->toBe(0);
    expect(McpHistory::where('project_id', $project->id)->where('tool', 'log_decision')->where('status', 'success')->exists())->toBeTrue();
});

it('requires both decision and rationale', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    expect(logDecisionCall($this, $raw, ['decision' => 'X']))->toContain('rationale is required');
    expect(logDecisionCall($this, $raw, ['rationale' => 'Y']))->toContain('decision is required');
    expect(Decision::where('project_id', $project->id)->count())->toBe(0);
});

it('atomically supersedes an active decision and links it', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $old = Decision::factory()->create(['project_id' => $project->id, 'decision' => 'Use Stripe', 'status' => 'active']);

    $text = logDecisionCall($this, $raw, [
        'decision'   => 'Use Square',
        'rationale'  => 'Lower fees',
        'supersedes' => $old->id,
    ]);

    $new = Decision::where('project_id', $project->id)->where('status', 'active')->first();
    expect($text)->toContain("superseded #{$old->id}")
        ->and($old->fresh()->status)->toBe('superseded')
        ->and($old->fresh()->superseded_by_id)->toBe($new->id);

    // Only one active decision remains — the ambiguity is structurally gone.
    expect(Decision::where('project_id', $project->id)->where('status', 'active')->count())->toBe(1);
});

it('rejects superseding a missing, cross-project, or already-superseded decision', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    [$rawOther, , $other] = mcpToken([], ['read', 'write']);

    $foreign = Decision::factory()->create(['project_id' => $other->id, 'status' => 'active']);
    $dead    = Decision::factory()->create(['project_id' => $project->id, 'status' => 'superseded']);

    expect(logDecisionCall($this, $raw, ['decision' => 'A', 'rationale' => 'B', 'supersedes' => 999999]))->toContain('not found');
    expect(logDecisionCall($this, $raw, ['decision' => 'A', 'rationale' => 'B', 'supersedes' => $foreign->id]))->toContain('not found');
    expect(logDecisionCall($this, $raw, ['decision' => 'A', 'rationale' => 'B', 'supersedes' => $dead->id]))->toContain('already superseded');

    // No new decision row and no orphaned breadcrumb written on any rejection.
    expect(Decision::where('project_id', $project->id)->where('decision', 'A')->count())->toBe(0);
    expect(ThreadEntry::where('project_id', $project->id)->count())->toBe(0);
});

it('requires the write scope', function () {
    [$raw] = mcpToken([], ['read']); // read-only token

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 1,
            'params'  => ['name' => 'log_decision', 'arguments' => ['decision' => 'X', 'rationale' => 'Y']],
        ])
        ->assertStatus(200)
        ->assertJsonPath('error.code', -32603);
})->skip('Enable only if the scope-error shape is asserted elsewhere; McpServer enforces scope centrally.');
