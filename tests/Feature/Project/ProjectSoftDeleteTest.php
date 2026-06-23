<?php

use App\Events\ProjectDeleted;
use App\Models\Project;
use Illuminate\Support\Facades\Event;

it('soft-deletes a project instead of removing the row', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $project->delete();

    expect(Project::find($project->id))->toBeNull();
    expect(Project::withTrashed()->find($project->id))->not->toBeNull();
    expect(Project::withTrashed()->find($project->id)->deleted_at)->not->toBeNull();
});

it('excludes trashed projects from the index listing', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->getJson('/api/v1/projects')->assertStatus(200)->assertJsonCount(1, 'data');

    $project->delete();

    $this->getJson('/api/v1/projects')->assertStatus(200)->assertJsonCount(0, 'data');
});

it('returns 404 for any access to a trashed project', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $project->delete();

    $this->getJson("/api/v1/projects/{$project->id}")->assertStatus(404);
    $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'x'])->assertStatus(404);
});

it('owner soft-deletes via the endpoint, logs activity, and broadcasts', function () {
    Event::fake([ProjectDeleted::class]);

    $user    = actingAsUser();
    $project = createProject($user);

    $this->deleteJson("/api/v1/projects/{$project->id}")
        ->assertStatus(200)
        ->assertJson(['message' => 'Project deleted.']);

    expect(\App\Models\Project::find($project->id))->toBeNull();

    $this->assertDatabaseHas('activity_logs', [
        'project_id' => $project->id,
        'event_type' => 'project.deleted',
    ]);

    Event::assertDispatched(ProjectDeleted::class, function ($e) use ($project, $user) {
        return $e->projectId === $project->id && in_array($user->id, $e->memberIds, true);
    });
});

it('forbids a non-owner member from deleting', function () {
    ['project' => $project, 'member' => $member] = createProjectWithMember();
    \Laravel\Sanctum\Sanctum::actingAs($member);

    $this->deleteJson("/api/v1/projects/{$project->id}")->assertStatus(403);
});
