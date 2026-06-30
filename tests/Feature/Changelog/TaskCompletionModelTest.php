<?php

use App\Models\TaskCompletion;

it('creates a task completion via factory with no updated_at', function () {
    $completion = TaskCompletion::factory()->create();

    expect($completion->source)->toBe('claude')
        ->and($completion->summary_what)->not->toBeNull()
        ->and($completion->task)->not->toBeNull()
        ->and($completion->getAttributes())->not->toHaveKey('updated_at');
});
