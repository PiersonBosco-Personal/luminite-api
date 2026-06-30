<?php

use App\Events\TaskCompletionRecorded;
use App\Models\TaskCompletion;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the private project channel as TaskCompletionRecorded', function () {
    $completion = TaskCompletion::factory()->create();

    $event = new TaskCompletionRecorded($completion, 77);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('project.77')])
        ->and($event->broadcastAs())->toBe('TaskCompletionRecorded')
        ->and($event->broadcastWith()['completion']['id'])->toBe($completion->id);
});
