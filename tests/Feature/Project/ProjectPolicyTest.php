<?php

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;

it('allows only the owner to restore and force-delete', function () {
    $owner   = User::factory()->create();
    $member  = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $policy = new ProjectPolicy();

    expect($policy->restore($owner, $project))->toBeTrue();
    expect($policy->restore($member, $project))->toBeFalse();
    expect($policy->forceDelete($owner, $project))->toBeTrue();
    expect($policy->forceDelete($member, $project))->toBeFalse();
});
