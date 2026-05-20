<?php

use App\Models\TimeEntry;

it('stops the active timer and calculates duration', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    TimeEntry::factory()->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'task_id'          => \App\Models\Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id])->id,
        'duration_minutes' => null,
        'started_at'       => now()->subMinutes(45),
        'stopped_at'       => null,
    ]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/time-entries/timer/stop")
        ->assertStatus(200);

    expect($response->json('data.duration_minutes'))->toBeGreaterThanOrEqual(44);
    expect($response->json('data.duration_minutes'))->toBeLessThanOrEqual(46);
    expect($response->json('data.stopped_at'))->not->toBeNull();
    expect($response->json('data.is_running'))->toBeFalse();
});

it('returns 404 when no active timer exists', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/time-entries/timer/stop")
        ->assertStatus(404);
});

it('only stops the active timer for the calling user', function () {
    $userA   = actingAsUser();
    $userB   = \App\Models\User::factory()->create();
    $project = createProject($userA);
    addMemberToProject($project, $userB);

    TimeEntry::factory()->running()->create([
        'project_id' => $project->id,
        'task_id'    => \App\Models\Task::factory()->create(['project_id' => $project->id, 'created_by' => $userA->id])->id,
        'user_id'    => $userB->id,
    ]);

    $this->postJson("/api/v1/projects/{$project->id}/time-entries/timer/stop")
        ->assertStatus(404);
});
