<?php

use App\Models\ActivityLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns paginated activity logs for a project member', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    ActivityLog::factory()->count(3)->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
    ]);

    $response = $this->getJson("/api/v1/projects/{$project->id}/activity")
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('meta.total'))->toBe(3);
});

it('returns 403 for non-members', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/activity")
        ->assertStatus(403);
});

it('filters by tasks category', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'event_type' => 'task.completed']);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'event_type' => 'section.created']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/activity?category=tasks")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['event_type'])->toBe('task.completed');
});

it('filters by mcp category', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'via_mcp' => true, 'event_type' => 'task.completed']);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'via_mcp' => false, 'event_type' => 'task.created']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/activity?category=mcp")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(1);
});

it('filters by labels_sections category', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'event_type' => 'label.created']);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'event_type' => 'section.deleted']);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'event_type' => 'task.completed']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/activity?category=labels_sections")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(2);
});

it('filters by tech_stack category', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'event_type' => 'tech_stack.added']);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'event_type' => 'task.completed']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/activity?category=tech_stack")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(1);
});

it('searches activity by description keyword', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'description' => 'Pierson completed Build login page']);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'description' => 'Pierson created section Backlog']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/activity?search=login")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['description'])->toContain('login');
});

it('scopes activity to specific user ids', function () {
    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($owner);

    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/activity?user_ids[]={$owner->id}")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(1);
});

it('returns most recent entry first', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $old = ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'description' => 'old']);
    ActivityLog::factory()->create(['project_id' => $project->id, 'user_id' => $user->id, 'description' => 'new']);

    \DB::table('activity_logs')->where('id', $old->id)->update(['created_at' => now()->subHour()]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/activity")
        ->assertStatus(200)
        ->json('data');

    expect($data[0]['description'])->toBe('new');
});
