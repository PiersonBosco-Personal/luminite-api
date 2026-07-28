<?php

use App\AI\Contracts\AIProvider;
use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Embedding;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\ThreadEntry;
use App\Models\User;

it('embeds a decision using "decision\nrationale" as the text', function () {
    $decision = Decision::factory()->create(['decision' => 'Use Square', 'rationale' => 'Lower fees']);
    clearEmbeddingIndex(); // otherwise the create observer's row makes this a no-op re-embed

    $mock = Mockery::mock(AIProvider::class);
    $mock->shouldReceive('embed')->once()
        ->with("Use Square\nLower fees")
        ->andReturn(array_fill(0, 1536, 0.5));
    app()->instance(AIProvider::class, $mock);

    (new EmbedRecord('decision', $decision->id))->handle();
    // embed() must have been called with the right text; the row lands only on pgvector.
    expect(Embedding::count())->toBe(persistsVectors() ? 1 : 0);
});

it('embeds a thread entry using its content', function () {
    $entry = ThreadEntry::factory()->create(['type' => 'gotcha', 'content' => 'Reverb needs backoff']);
    clearEmbeddingIndex();

    $mock = Mockery::mock(AIProvider::class);
    $mock->shouldReceive('embed')->once()->with('Reverb needs backoff')->andReturn(array_fill(0, 1536, 0.5));
    app()->instance(AIProvider::class, $mock);

    (new EmbedRecord('thread_entry', $entry->id))->handle();
    expect(Embedding::count())->toBe(persistsVectors() ? 1 : 0);
});

it('is idempotent — unchanged text is not re-embedded', function () {
    $decision = Decision::factory()->create(['decision' => 'Use Square', 'rationale' => 'Lower fees']);
    clearEmbeddingIndex();
    // Pre-seed an index row whose hash matches the current text.
    Embedding::factory()->create([
        'source_type'  => 'decision',
        'source_id'    => $decision->id,
        'content_hash' => hash('sha256', "Use Square\nLower fees"),
    ]);

    $mock = Mockery::mock(AIProvider::class);
    $mock->shouldNotReceive('embed');
    app()->instance(AIProvider::class, $mock);

    (new EmbedRecord('decision', $decision->id))->handle();
});

it('no-ops when the source row was deleted before the job ran', function () {
    $mock = Mockery::mock(AIProvider::class);
    $mock->shouldNotReceive('embed');
    app()->instance(AIProvider::class, $mock);

    (new EmbedRecord('decision', 999999))->handle(); // must not throw
    expect(Embedding::count())->toBe(0);
});

it('embeds a task using its title and description', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $task = Task::factory()->create([
        'project_id'  => $project->id,
        'section_id'  => $section->id,
        'created_by'  => $user->id,
        'title'       => 'Add rate limiting',
        'description' => 'Throttle the public API to 60 requests per minute.',
    ]);
    clearEmbeddingIndex();

    $mock = Mockery::mock(AIProvider::class);
    $mock->shouldReceive('embed')->once()
        ->with("Add rate limiting\nThrottle the public API to 60 requests per minute.")
        ->andReturn(array_fill(0, 1536, 0.5));
    app()->instance(AIProvider::class, $mock);

    (new EmbedRecord('task', $task->id))->handle();
    expect(Embedding::count())->toBe(persistsVectors() ? 1 : 0);
});

it('composes task text with no trailing newline when the description is null', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $task = Task::factory()->create([
        'project_id'  => $project->id,
        'section_id'  => $section->id,
        'created_by'  => $user->id,
        'title'       => 'Add rate limiting',
        'description' => null,
    ]);

    expect(EmbedRecord::textFor('task', $task))->toBe('Add rate limiting');
});

it('declares ShouldBeUnique with a per-source id and a 300s dedupe window', function () {
    $job = new EmbedRecord('task', 42);

    expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('task:42')
        ->and($job->uniqueFor)->toBe(300)
        ->and(EmbedRecord::DEDUPE_WINDOW_SECONDS)->toBe(300);
});

it('defers dispatch until after the writing transaction commits', function () {
    // Observers dispatch from inside the transaction. Without afterCommit a
    // worker can beat the commit, find() returns null, and the record is
    // silently never indexed. Nothing else in the suite would catch a regression
    // here, because tests run on the sync queue where the race cannot occur.
    expect((new EmbedRecord('task', 42))->afterCommit)->toBeTrue();
});
