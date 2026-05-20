<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\TimeEntryPolicy;

it('allows the owner of an entry to update it', function () {
    $owner   = User::factory()->create();
    $project = createProject($owner);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
    ]);

    $policy = new TimeEntryPolicy();
    expect($policy->update($owner, $entry))->toBeTrue();
});

it('forbids another project member from updating the entry', function () {
    $owner   = User::factory()->create();
    $other   = User::factory()->create();
    $project = createProject($owner);
    addMemberToProject($project, $other);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
    ]);

    $policy = new TimeEntryPolicy();
    expect($policy->update($other, $entry))->toBeFalse();
});

it('allows the owner to delete the entry and forbids others', function () {
    $owner   = User::factory()->create();
    $other   = User::factory()->create();
    $project = createProject($owner);
    addMemberToProject($project, $other);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
    ]);

    $policy = new TimeEntryPolicy();
    expect($policy->delete($owner, $entry))->toBeTrue();
    expect($policy->delete($other, $entry))->toBeFalse();
});
