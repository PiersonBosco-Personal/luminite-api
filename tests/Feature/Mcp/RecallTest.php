<?php

use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Embedding;
use Illuminate\Support\Facades\DB;

function recallCall($test, string $raw, array $arguments): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'recall', 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('requires a query', function () {
    [$raw] = mcpToken([], ['read']);
    expect(recallCall($this, $raw, ['query' => '   ']))->toContain('query is required');
});

it('is callable with a read-only token', function () {
    [$raw] = mcpToken([], ['read']);
    // Read scope must be sufficient — no -32603 scope error.
    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 1,
            'params'  => ['name' => 'recall', 'arguments' => ['query' => 'anything']],
        ])
        ->assertStatus(200)
        ->assertJsonMissingPath('error');
});

it('returns a clean message on a non-pgsql driver (no similarity query)', function () {
    [$raw] = mcpToken([], ['read']);
    expect(DB::connection()->getDriverName())->not->toBe('pgsql'); // guard: this test is about the SQLite path
    expect(recallCall($this, $raw, ['query' => 'auth tokens']))->toContain('No indexed memory');
});

// --- pgsql-gated: real similarity ranking + active-filtering (runs on CI/Postgres) ---

it('ranks the nearest embedding first and active-filters superseded decisions', function () {
    [$raw, , $project, $user] = mcpToken([], ['read']);

    // Two decisions with hand-crafted, distinct unit vectors.
    $near = Decision::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'decision' => 'NEAR match', 'status' => 'active']);
    $far  = Decision::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'decision' => 'FAR match', 'status' => 'active']);

    insertEmbedding($project->id, 'decision', $near->id, hotVector(0));
    insertEmbedding($project->id, 'decision', $far->id, hotVector(5));

    // Mock the query embedding to sit exactly on $near's vector.
    $mock = Mockery::mock(App\AI\Contracts\AIProvider::class);
    $mock->shouldReceive('embed')->andReturn(hotArray(0));
    app()->instance(App\AI\Contracts\AIProvider::class, $mock);

    $out = recallCall($this, $raw, ['query' => 'whatever']);
    expect($out)->toContain('NEAR match');
    // NEAR appears before FAR in the ranked output.
    expect(strpos($out, 'NEAR match'))->toBeLessThan(strpos($out, 'FAR match'));

    // Now supersede NEAR — it must drop out of default recall, FAR surfaces.
    $near->update(['status' => 'superseded']);
    $out2 = recallCall($this, $raw, ['query' => 'whatever']);
    expect($out2)->not->toContain('NEAR match')->and($out2)->toContain('FAR match');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'pgvector similarity requires PostgreSQL');

// Helpers for the pgsql-gated test.
function hotArray(int $hot): array
{
    $v = array_fill(0, 1536, 0.0);
    $v[$hot] = 1.0;
    return $v;
}
function hotVector(int $hot): string
{
    return '[' . implode(',', hotArray($hot)) . ']';
}
function insertEmbedding(int $projectId, string $type, int $id, string $literal): void
{
    DB::table('embeddings')->insert([
        'project_id'   => $projectId,
        'source_type'  => $type,
        'source_id'    => $id,
        'content_hash' => hash('sha256', (string) $id),
        'embedding'    => DB::raw("'{$literal}'::vector"),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
}

it('advertises a types filter in its input schema', function () {
    $definition = (new App\Mcp\Tools\Recall)->definition();

    expect($definition['inputSchema']['properties'])->toHaveKey('types')
        ->and($definition['inputSchema']['properties']['types']['type'])->toBe('array')
        ->and($definition['inputSchema']['properties']['types']['items']['enum'])
        ->toBe(['decision', 'gotcha', 'dead_end', 'task'])
        ->and($definition['inputSchema']['required'])->toBe(['query']);
});

it('rejects an unknown source type', function () {
    [$raw] = mcpToken([], ['read']);

    $text = callTool($this, $raw, 'recall', ['query' => 'anything', 'types' => ['banana']]);

    expect($text)->toContain('Error: types must be any of decision, gotcha, dead_end, task.');
});

it('still requires a query', function () {
    [$raw] = mcpToken([], ['read']);

    $text = callTool($this, $raw, 'recall', ['query' => '   ']);

    expect($text)->toBe('Error: query is required.');
});

it('excludes superseded decisions without shrinking the result set', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Vector ranking requires pgvector on PostgreSQL.');
    }

    [$raw, , $project, $user] = mcpToken([], ['read']);

    $superseded = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'decision'   => 'Use Stripe as the payment processor',
        'rationale'  => 'Best known brand',
        'status'     => 'superseded',
    ]);

    $active = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'decision'   => 'Use Square as the payment processor',
        'rationale'  => 'Lower fees at our volume',
        'status'     => 'active',
    ]);

    foreach ([$superseded, $active] as $d) {
        (new EmbedRecord('decision', $d->id))->handle();
    }

    $text = callTool($this, $raw, 'recall', ['query' => 'which payment vendor did we choose']);

    expect($text)->toContain('Square')
        ->and($text)->not->toContain('Stripe');
});
