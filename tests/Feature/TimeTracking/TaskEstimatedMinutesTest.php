<?php

use App\Models\Task;
use App\Models\User;

it('allows setting and reading estimated_minutes on a task', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $task    = Task::factory()->create([
        'project_id'        => $project->id,
        'created_by'        => $user->id,
        'estimated_minutes' => 240,
    ]);

    expect($task->fresh()->estimated_minutes)->toBe(240);
});
