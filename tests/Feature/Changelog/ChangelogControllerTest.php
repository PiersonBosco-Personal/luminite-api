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

    expect($data)->toHaveCount(1)
        ->and($data[0]['summary_what'])->toBe('newer');
});

it('blocks non-members from the changelog', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/changelog")->assertStatus(403);
});

it('first digest view shows recent completions by others, excluding old and own', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $coworker = User::factory()->create();
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $coworker->id, 'role' => 'member']);

    $recent = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $recent->id, 'completed_by_user_id' => $coworker->id,
        'summary_what' => 'recent change', 'source' => 'claude', 'created_at' => now()->subDay(),
    ]);
    $old = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $old->id, 'completed_by_user_id' => $coworker->id,
        'summary_what' => 'old change', 'source' => 'claude', 'created_at' => now()->subDays(30),
    ]);
    $mine = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $mine->id, 'completed_by_user_id' => $user->id,
        'summary_what' => 'mine', 'source' => 'human', 'created_at' => now()->subHour(),
    ]);

    $res = $this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->assertStatus(200);
    $whats = collect($res->json('data'))->pluck('summary_what')->all();

    expect($whats)->toContain('recent change')
        ->and($whats)->not->toContain('old change')
        ->and($whats)->not->toContain('mine')
        ->and($res->json('meta.unread_count'))->toBe(1);
});

it('a digest read does not advance the anchor; only viewing does', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $coworker = User::factory()->create();
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $coworker->id, 'role' => 'member']);

    $task = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $task->id, 'completed_by_user_id' => $coworker->id,
        'summary_what' => 'recent', 'source' => 'claude', 'created_at' => now()->subDay(),
    ]);

    $this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->assertStatus(200);
    $second = $this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->assertStatus(200);
    expect($second->json('meta.unread_count'))->toBe(1);

    $member = ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->first();
    expect($member?->last_viewed_changelog_at)->toBeNull();
});

it('viewing advances the anchor and clears the digest', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $coworker = User::factory()->create();
    ProjectMember::create(['project_id' => $project->id, 'user_id' => $coworker->id, 'role' => 'member']);

    $task = Task::factory()->create(['project_id' => $project->id]);
    TaskCompletion::factory()->create([
        'task_id' => $task->id, 'completed_by_user_id' => $coworker->id,
        'summary_what' => 'unseen', 'source' => 'claude', 'created_at' => now()->subDay(),
    ]);

    expect($this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->json('meta.unread_count'))->toBe(1);

    $this->postJson("/api/v1/projects/{$project->id}/changelog/viewed")->assertStatus(200);

    expect($this->getJson("/api/v1/projects/{$project->id}/changelog/digest")->json('meta.unread_count'))->toBe(0);
});
