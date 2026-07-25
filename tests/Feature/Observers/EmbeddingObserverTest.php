<?php

use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Embedding;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\ThreadEntry;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function makeTask(int $projectId, int $userId, array $overrides = []): Task
{
    $section = TaskSection::factory()->create(['project_id' => $projectId]);

    return Task::factory()->create(array_merge([
        'project_id' => $projectId,
        'section_id' => $section->id,
        'created_by' => $userId,
        'title'      => 'Original title',
    ], $overrides));
}

it('dispatches an embed job when a task is created directly on the model', function () {
    Queue::fake();
    $user    = User::factory()->create();
    $project = createProject($user);

    $task = makeTask($project->id, $user->id);

    Queue::assertPushed(
        EmbedRecord::class,
        fn ($job) => $job->sourceType === 'task' && $job->sourceId === $task->id
    );
});

it('does NOT dispatch when only a task position or status changes', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $task    = makeTask($project->id, $user->id);

    Queue::fake(); // fake AFTER creation so we only observe the update

    $task->update(['position' => 5, 'status' => 'in_progress']);

    Queue::assertNotPushed(EmbedRecord::class);
});

it('dispatches when a task title actually changes', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $task    = makeTask($project->id, $user->id);

    Queue::fake();

    $task->update(['title' => 'A genuinely different title']);

    Queue::assertPushed(EmbedRecord::class);
});

it('does NOT dispatch for momentum thread entries', function () {
    Queue::fake();
    $user    = User::factory()->create();
    $project = createProject($user);

    ThreadEntry::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'type'       => 'momentum',
        'content'    => 'Left off mid-refactor',
    ]);

    Queue::assertNotPushed(EmbedRecord::class);
});

it('dispatches for gotcha thread entries', function () {
    Queue::fake();
    $user    = User::factory()->create();
    $project = createProject($user);

    ThreadEntry::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'type'       => 'gotcha',
        'content'    => 'Reverb needs backoff',
    ]);

    Queue::assertPushed(EmbedRecord::class, fn ($job) => $job->sourceType === 'thread_entry');
});

it('deletes the embedding row when its source is deleted', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    $decision = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    Embedding::factory()->create([
        'project_id'   => $project->id,
        'source_type'  => 'decision',
        'source_id'    => $decision->id,
        'content_hash' => 'abc',
    ]);

    $decision->delete();

    expect(Embedding::where('source_type', 'decision')->where('source_id', $decision->id)->exists())
        ->toBeFalse();
});
