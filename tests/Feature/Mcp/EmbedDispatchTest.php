<?php

use App\Jobs\EmbedRecord;
use Illuminate\Support\Facades\Queue;

function callTool($test, string $raw, string $name, array $arguments): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => $name, 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('dispatches an embed job when a decision is logged', function () {
    Queue::fake();
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    callTool($this, $raw, 'log_decision', ['decision' => 'Use Square', 'rationale' => 'Lower fees']);

    Queue::assertPushed(EmbedRecord::class, function ($job) use ($project) {
        return $job->sourceType === 'decision'
            && $job->sourceId === App\Models\Decision::where('project_id', $project->id)->value('id');
    });
});

it('dispatches an embed job for gotcha and dead_end thread entries', function () {
    Queue::fake();
    [$raw] = mcpToken([], ['read', 'write']);

    callTool($this, $raw, 'add_thread_entry', ['type' => 'gotcha', 'content' => 'Reverb needs backoff']);
    callTool($this, $raw, 'add_thread_entry', ['type' => 'dead_end', 'content' => 'Polling was too slow']);

    Queue::assertPushed(EmbedRecord::class, 2);
    Queue::assertPushed(EmbedRecord::class, fn ($job) => $job->sourceType === 'thread_entry');
});

it('does NOT dispatch an embed job for momentum entries', function () {
    Queue::fake();
    [$raw] = mcpToken([], ['read', 'write']);

    callTool($this, $raw, 'add_thread_entry', ['type' => 'momentum', 'content' => 'Where I left off']);

    Queue::assertNotPushed(EmbedRecord::class);
});

it('does NOT dispatch for the momentum breadcrumb that log_decision writes', function () {
    Queue::fake();
    [$raw] = mcpToken([], ['read', 'write']);

    callTool($this, $raw, 'log_decision', ['decision' => 'Use Square', 'rationale' => 'Lower fees']);

    // Exactly one job — for the decision, not for its momentum breadcrumb.
    Queue::assertPushed(EmbedRecord::class, 1);
});
