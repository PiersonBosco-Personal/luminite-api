<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;

it('lists work types for a project', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Development']);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Testing']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/work-types")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(2);
});

it('does not list work types from other projects', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $other   = createProject(User::factory()->create());

    WorkType::factory()->create(['project_id' => $other->id]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/work-types")
        ->assertStatus(200)
        ->json('data');

    expect($data)->toHaveCount(0);
});

it('returns 403 on work type index when not a member', function () {
    actingAsUser();
    $project = createProject(User::factory()->create());

    $this->getJson("/api/v1/projects/{$project->id}/work-types")->assertStatus(403);
});

it('includes time_entries_count on each work type', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $dev   = WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Development']);
    $other = WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Other']);

    TimeEntry::factory()->count(3)->create([
        'project_id'   => $project->id,
        'user_id'      => $user->id,
        'work_type_id' => $dev->id,
    ]);

    $data = collect(
        $this->getJson("/api/v1/projects/{$project->id}/work-types")
            ->assertStatus(200)
            ->json('data')
    )->keyBy('id');

    expect($data[$dev->id]['time_entries_count'])->toBe(3);
    expect($data[$other->id]['time_entries_count'])->toBe(0);
});

it('orders active work types before inactive ones', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Archived', 'is_active' => false]);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Zebra', 'is_active' => true]);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Apple', 'is_active' => true]);

    $names = collect(
        $this->getJson("/api/v1/projects/{$project->id}/work-types")
            ->assertStatus(200)
            ->json('data')
    )->pluck('name')->all();

    expect($names)->toBe(['Apple', 'Zebra', 'Archived']);
});
