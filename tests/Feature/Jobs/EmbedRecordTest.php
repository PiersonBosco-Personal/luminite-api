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

    $mock = Mockery::mock(AIProvider::class);
    $mock->shouldReceive('embed')->once()
        ->with("Use Square\nLower fees")
        ->andReturn(array_fill(0, 1536, 0.5));
    app()->instance(AIProvider::class, $mock);

    (new EmbedRecord('decision', $decision->id))->handle();
    // On SQLite the vector is not persisted, but embed() must have been called with the right text.
    expect(Embedding::count())->toBe(0);
});

it('embeds a thread entry using its content', function () {
    $entry = ThreadEntry::factory()->create(['type' => 'gotcha', 'content' => 'Reverb needs backoff']);

    $mock = Mockery::mock(AIProvider::class);
    $mock->shouldReceive('embed')->once()->with('Reverb needs backoff')->andReturn(array_fill(0, 1536, 0.5));
    app()->instance(AIProvider::class, $mock);

    (new EmbedRecord('thread_entry', $entry->id))->handle();
    expect(Embedding::count())->toBe(0);
});

it('is idempotent — unchanged text is not re-embedded', function () {
    $decision = Decision::factory()->create(['decision' => 'Use Square', 'rationale' => 'Lower fees']);
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

    // NB: constructor property promotion cannot be by-reference in PHP, so the
    // fake records into its own property rather than capturing an outer variable.
    $fake = new class implements AIProvider {
        public array $seen = [];

        public function embed(string $text): array
        {
            $this->seen[] = $text;
            return array_fill(0, 1536, 0.01);
        }
    };
    app()->instance(AIProvider::class, $fake);

    (new EmbedRecord('task', $task->id))->handle();

    expect($fake->seen[0])->toBe("Add rate limiting\nThrottle the public API to 60 requests per minute.");
});
