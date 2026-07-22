<?php

use App\Models\Embedding;
use Illuminate\Support\Facades\Schema;

it('creates the embeddings table (without the vector column under SQLite)', function () {
    expect(Schema::hasTable('embeddings'))->toBeTrue()
        ->and(Schema::hasColumns('embeddings', ['project_id', 'source_type', 'source_id', 'content_hash']))->toBeTrue();

    // The vector column is pgsql-only; under the SQLite suite it must be absent.
    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(Schema::hasColumn('embeddings', 'embedding'))->toBeFalse();
    }
});

it('persists an embedding index row pointing at a source', function () {
    $row = Embedding::factory()->create([
        'source_type'  => 'decision',
        'source_id'    => 123,
        'content_hash' => str_repeat('a', 64),
    ]);

    expect($row->fresh())->not->toBeNull()
        ->and($row->source_type)->toBe('decision')
        ->and($row->source_id)->toBe(123);
});

it('enforces one embedding per source row', function () {
    Embedding::factory()->create(['source_type' => 'decision', 'source_id' => 7]);

    expect(fn () => Embedding::factory()->create(['source_type' => 'decision', 'source_id' => 7]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
