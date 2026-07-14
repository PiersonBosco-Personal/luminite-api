<?php

use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

function inviteOn($project, string $email, array $overrides = []): ProjectInvitation
{
    return ProjectInvitation::create(array_merge([
        'project_id' => $project->id,
        'invited_by' => $project->owner_id,
        'email'      => $email,
        'expires_at' => Carbon::now()->addDays(7),
    ], $overrides));
}

it('owner can list project invitations with status', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    inviteOn($project, 'pending@example.com');
    inviteOn($project, 'declined@example.com', ['declined_at' => now()]);

    $this->getJson("/api/v1/projects/{$project->id}/invitations")
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['email' => 'pending@example.com', 'status' => 'pending'])
        ->assertJsonFragment(['email' => 'declined@example.com', 'status' => 'declined']);
});

it('does not list accepted invitations in the owner list', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    inviteOn($project, 'pending@example.com');
    inviteOn($project, 'accepted@example.com', ['accepted_at' => now()]);

    $this->getJson("/api/v1/projects/{$project->id}/invitations")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['email' => 'pending@example.com'])
        ->assertJsonMissing(['email' => 'accepted@example.com']);
});

it('returns 403 when a non-owner lists invitations', function () {
    ['project' => $project, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($member);

    $this->getJson("/api/v1/projects/{$project->id}/invitations")->assertStatus(403);
});

it('owner can resend an invitation, re-sending the email and resetting status', function () {
    Mail::fake();
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $invite = inviteOn($project, 'declined@example.com', ['declined_at' => now(), 'expires_at' => now()->subDay()]);

    $this->postJson("/api/v1/projects/{$project->id}/invitations/{$invite->id}/resend")
        ->assertStatus(200)
        ->assertJsonFragment(['status' => 'pending']);

    Mail::assertQueued(\App\Mail\ProjectInvitationMail::class);
    expect($invite->fresh()->declined_at)->toBeNull();
    expect($invite->fresh()->expires_at->isFuture())->toBeTrue();
});

it('owner can cancel (delete) an invitation', function () {
    ['project' => $project, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $invite = inviteOn($project, 'pending@example.com');

    $this->deleteJson("/api/v1/projects/{$project->id}/invitations/{$invite->id}")
        ->assertStatus(200);

    expect(ProjectInvitation::find($invite->id))->toBeNull();
});

it('returns 404 when managing an invitation from another project', function () {
    ['project' => $projectA, 'owner' => $owner] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $other    = User::factory()->create();
    $projectB = createProject($other);
    $invite   = inviteOn($projectB, 'x@example.com');

    $this->deleteJson("/api/v1/projects/{$projectA->id}/invitations/{$invite->id}")
        ->assertStatus(404);
});
