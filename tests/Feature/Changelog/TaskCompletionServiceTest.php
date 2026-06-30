<?php

use App\Events\TaskCompletionRecorded;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Services\TaskCompletionService;
use Illuminate\Support\Facades\Event;

it('records a completion row and broadcasts, trimming blanks to null', function () {
    Event::fake([TaskCompletionRecorded::class]);
    $task = Task::factory()->create();

    $completion = app(TaskCompletionService::class)->record(
        task: $task,
        userId: $task->project->owner_id ?? \App\Models\User::factory()->create()->id,
        what: '   ',          // whitespace → null
        why: 'because reasons',
        source: 'claude',
    );

    expect($completion)->toBeInstanceOf(TaskCompletion::class)
        ->and($completion->summary_what)->toBeNull()
        ->and($completion->summary_why)->toBe('because reasons')
        ->and($completion->source)->toBe('claude')
        ->and(TaskCompletion::where('task_id', $task->id)->count())->toBe(1);

    Event::assertDispatched(TaskCompletionRecorded::class, fn ($e) =>
        $e->completion->id === $completion->id && $e->projectId === $task->project_id);
});

it('appends a new row on re-completion (does not overwrite)', function () {
    Event::fake([TaskCompletionRecorded::class]);
    $task = Task::factory()->create();
    $svc  = app(TaskCompletionService::class);
    $uid  = \App\Models\User::factory()->create()->id;

    $svc->record(task: $task, userId: $uid, what: 'first', why: null, source: 'claude');
    $svc->record(task: $task, userId: $uid, what: 'second', why: null, source: 'human');

    expect(TaskCompletion::where('task_id', $task->id)->count())->toBe(2);
});
