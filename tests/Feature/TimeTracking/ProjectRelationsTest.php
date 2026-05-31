<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;

it('exposes a workTypes relation on Project', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    WorkType::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->workTypes()->count())->toBe(3);
});

it('exposes a timeEntries relation on Project', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    TimeEntry::factory()->count(2)->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
    ]);

    expect($project->timeEntries()->count())->toBe(2);
});
