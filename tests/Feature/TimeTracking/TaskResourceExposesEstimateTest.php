<?php

use App\Models\Task;

it('exposes estimated_minutes in the task resource', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'estimated_minutes' => 180]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertStatus(200);

    expect($response->json('data.estimated_minutes'))->toBe(180);
});
