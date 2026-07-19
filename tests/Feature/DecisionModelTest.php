<?php

use App\Models\Decision;

it('persists a decision with lifecycle columns and resolves the self-relation', function () {
    $old = Decision::factory()->create(['decision' => 'Use Stripe', 'rationale' => 'Familiar']);
    $new = Decision::factory()->create([
        'project_id'       => $old->project_id,
        'decision'         => 'Use Square',
        'rationale'        => 'Lower fees',
        'superseded_by_id' => null,
    ]);
    $old->update(['status' => 'superseded', 'superseded_by_id' => $new->id]);

    expect($old->fresh())
        ->status->toBe('superseded')
        ->and($old->fresh()->supersededBy->id)->toBe($new->id)
        ->and($new->fresh()->status)->toBe('active');

    expect(Decision::STATUSES)->toBe(['active', 'superseded']);
});
