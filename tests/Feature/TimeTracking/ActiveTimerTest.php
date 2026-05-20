<?php

use App\Models\TimeEntry;

it('returns the active timer for the authenticated user across all projects', function () {
    $user      = actingAsUser();
    $projectA  = createProject($user);
    $projectB  = createProject($user);

    TimeEntry::factory()->running()->create([
        'project_id' => $projectB->id,
        'task_id'    => \App\Models\Task::factory()->create(['project_id' => $projectB->id, 'created_by' => $user->id])->id,
        'user_id'    => $user->id,
    ]);

    $response = $this->getJson('/api/v1/user/active-timer')->assertStatus(200);
    expect($response->json('data.project_id'))->toBe($projectB->id);
    expect($response->json('data.is_running'))->toBeTrue();
});

it('returns null data when the user has no active timer', function () {
    actingAsUser();

    $this->getJson('/api/v1/user/active-timer')
        ->assertStatus(200)
        ->assertJson(['data' => null]);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/user/active-timer')->assertStatus(401);
});
