<?php

use App\Models\ThreadEntry;

it('persists a thread entry with all columns', function () {
    $entry = ThreadEntry::factory()->create([
        'type'    => 'decision',
        'content' => 'Chose project-scoped stream over task-scoped.',
        'trigger' => 'manual',
    ]);

    expect($entry->fresh())
        ->type->toBe('decision')
        ->content->toBe('Chose project-scoped stream over task-scoped.')
        ->trigger->toBe('manual')
        ->and($entry->project_id)->not->toBeNull()
        ->and($entry->created_by)->not->toBeNull();

    expect(ThreadEntry::TYPES)->toBe(['momentum', 'decision', 'dead_end', 'gotcha']);
});
