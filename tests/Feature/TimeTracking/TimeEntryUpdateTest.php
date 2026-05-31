<?php

use App\Models\TimeEntry;
use App\Models\User;

it('allows owner to update their entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $entry   = TimeEntry::factory()->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'duration_minutes' => 30,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'duration_minutes' => 90,
    ])->assertStatus(200);

    expect($entry->fresh()->duration_minutes)->toBe(90);
});

it('forbids non-owner from updating entry', function () {
    $owner   = User::factory()->create();
    $other   = actingAsUser();
    $project = createProject($owner);
    addMemberToProject($project, $other);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'duration_minutes' => 60,
    ])->assertStatus(403);
});

it('returns 404 when entry does not belong to the project', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $other   = createProject($user);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $other->id,
        'user_id'    => $user->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'duration_minutes' => 60,
    ])->assertStatus(404);
});
