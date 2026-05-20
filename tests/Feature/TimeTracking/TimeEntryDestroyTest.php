<?php

use App\Models\TimeEntry;
use App\Models\User;

it('allows owner to delete their entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
    ]);

    $this->deleteJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}")->assertStatus(200);
    expect(TimeEntry::find($entry->id))->toBeNull();
});

it('forbids non-owner from deleting entry', function () {
    $owner   = User::factory()->create();
    $other   = actingAsUser();
    $project = createProject($owner);
    addMemberToProject($project, $other);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
    ]);

    $this->deleteJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}")->assertStatus(403);
});
