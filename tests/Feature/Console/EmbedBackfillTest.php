<?php

use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Embedding;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\ThreadEntry;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues embed jobs for un-indexed rows', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    Decision::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    ThreadEntry::factory()->create([
        'project_id' => $project->id, 'created_by' => $user->id, 'type' => 'gotcha',
    ]);
    ThreadEntry::factory()->create([
        'project_id' => $project->id, 'created_by' => $user->id, 'type' => 'momentum',
    ]);
    Task::factory()->create([
        'project_id' => $project->id, 'section_id' => $section->id, 'created_by' => $user->id,
    ]);

    clearEmbeddingIndex(); // the create observers already indexed these on pgvector
    Queue::fake(); // fake AFTER seeding so observer dispatches don't pollute the count

    $this->artisan('luminite:embed-backfill')->assertSuccessful();

    // decision + gotcha + task = 3. Momentum is never embeddable.
    Queue::assertPushed(EmbedRecord::class, 3);
});

it('skips rows that are already indexed with a current hash', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    $decision = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'decision'   => 'Use Square',
        'rationale'  => 'Lower fees',
    ]);

    clearEmbeddingIndex();

    Embedding::factory()->create([
        'project_id'   => $project->id,
        'source_type'  => 'decision',
        'source_id'    => $decision->id,
        'content_hash' => hash('sha256', "Use Square\nLower fees"),
    ]);

    Queue::fake();

    $this->artisan('luminite:embed-backfill')->assertSuccessful();

    Queue::assertNotPushed(EmbedRecord::class);
});

it('re-queues a row whose text changed since it was indexed', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    $decision = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'decision'   => 'Use Square',
        'rationale'  => 'Lower fees',
    ]);

    clearEmbeddingIndex();

    // Indexed under the OLD text — the hash no longer matches what the record says.
    Embedding::factory()->create([
        'project_id'   => $project->id,
        'source_type'  => 'decision',
        'source_id'    => $decision->id,
        'content_hash' => hash('sha256', "Use Stripe\nBetter known"),
    ]);

    Queue::fake();

    $this->artisan('luminite:embed-backfill')->assertSuccessful();

    Queue::assertPushed(
        EmbedRecord::class,
        fn ($job) => $job->sourceType === 'decision' && $job->sourceId === $decision->id
    );
});

it('can be scoped to a single project', function () {
    $user  = User::factory()->create();
    $mine  = createProject($user);
    $other = createProject($user);

    Decision::factory()->create(['project_id' => $mine->id, 'created_by' => $user->id]);
    Decision::factory()->create(['project_id' => $other->id, 'created_by' => $user->id]);

    clearEmbeddingIndex();
    Queue::fake();

    $this->artisan('luminite:embed-backfill', ['--project' => $mine->id])->assertSuccessful();

    Queue::assertPushed(EmbedRecord::class, 1);
});
