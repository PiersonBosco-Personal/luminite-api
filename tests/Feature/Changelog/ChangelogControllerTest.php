<?php

use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;

it('returns the archive feed newest-first for a member', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id]);

    TaskCompletion::factory()->create([
        'task_id' => $task->id, 'completed_by_user_id' => $user->id,
        'summary_what' => 'older', 'source' => 'claude', 'created_at' => now()->subMinutes(5),
    ]);
    TaskCompletion::factory()->create([
        'task_id' => $task->id, 'completed_by_user_id' => $user->id,
        'summary_what' => 'newer', 'source' => 'claude', 'created_at' => now(),
    ]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/changelog")
        ->assertStatus(200)->json('data');

    expect($data)->toHaveCount(1)                       // latest-canonical per task
        ->and($data[0]['summary_what'])->toBe('newer');
});

it('blocks non-members from the changelog', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/changelog")->assertStatus(403);
});

it('digest shows only the OTHER members completions and is empty on first read', function () {
    $user    = actingAsUser();
    $project = createProject($user);                    // $user is owner+member, anchor null
    $coworker = User::factory()->create();
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $coworker->id, 'role' => 'member']);

    $task = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $task->id, 'completed_by_user_id' => $coworker->id,
        'summary_what' => 'before view', 'source' => 'claude', 'created_at' => now()->subMinute(),
    ]);

    // First read: null anchor → baseline init (now), empty digest ("all caught up").
    $first = $this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->assertStatus(200);
    expect($first->json('data'))->toHaveCount(0)
        ->and($first->json('meta.unread_count'))->toBe(0);

    // A NEW coworker completion clearly AFTER the baseline now shows.
    $task2 = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $task2->id, 'completed_by_user_id' => $coworker->id,
        'summary_what' => 'after baseline', 'source' => 'claude', 'created_at' => now()->addMinute(),
    ]);

    $second = $this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->assertStatus(200);
    expect($second->json('data'))->toHaveCount(1)
        ->and($second->json('data.0.summary_what'))->toBe('after baseline')
        ->and($second->json('meta.unread_count'))->toBe(1);
});

it('viewed advances the anchor and clears the digest', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $coworker = User::factory()->create();
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $coworker->id, 'role' => 'member']);
    $this->getJson("/api/v1/projects/{$project->id}/changelog/digest"); // baseline init

    $task = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $task->id, 'completed_by_user_id' => $coworker->id,
        'summary_what' => 'unseen', 'source' => 'claude', 'created_at' => now()->addMinute(),
    ]);

    // Confirm it WOULD show before viewing.
    expect($this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->json('data'))->toHaveCount(1);

    // Travel past the completion's timestamp so "now" (the new anchor) is after it.
    $this->travelTo(now()->addMinutes(2));
    $this->postJson("/api/v1/projects/{$project->id}/changelog/viewed")->assertStatus(200);

    expect($this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->json('data'))->toHaveCount(0);
    $this->travelBack();
});
