<?php

use App\Models\TimeEntry;
use App\Models\User;

it('exposes a timeEntries relation on User', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    TimeEntry::factory()->count(4)->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
    ]);

    expect($user->timeEntries()->count())->toBe(4);
});
