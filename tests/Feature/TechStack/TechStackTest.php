<?php

use App\Models\TechStack;
use App\Models\User;

// --- Index ---

it('returns only root tech stack entries with nested children', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $root  = TechStack::factory()->create(['project_id' => $project->id, 'parent_id' => null]);
    TechStack::factory()->create(['project_id' => $project->id, 'parent_id' => $root->id]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/tech-stack")
                 ->assertStatus(200)
                 ->json('data');

    expect(count($data))->toBe(1)
        ->and($data[0]['id'])->toBe($root->id)
        ->and($data[0])->toHaveKey('children')
        ->and(count($data[0]['children']))->toBe(1);
});

it('returns 403 on index when not a member', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/tech-stack")->assertStatus(403);
});

// --- Store ---

it('member can create a root tech stack entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/tech-stack", [
        'name'    => 'Laravel',
        'version' => '11',
    ])->assertStatus(201)->assertJsonFragment(['name' => 'Laravel']);
});

it('member can create a child tech stack entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $root    = TechStack::factory()->create(['project_id' => $project->id]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/tech-stack", [
        'name'      => 'Sanctum',
        'parent_id' => $root->id,
    ])->assertStatus(201);

    expect($response->json('data.parent_id'))->toBe($root->id);
});

it('returns 422 when parent_id belongs to another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $foreign  = TechStack::factory()->create(['project_id' => $project2->id]);

    $this->postJson("/api/v1/projects/{$project->id}/tech-stack", [
        'name'      => 'Test',
        'parent_id' => $foreign->id,
    ])->assertStatus(422);
});

it('returns 422 when name is missing on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/tech-stack", [])->assertStatus(422);
});

// --- Update ---

it('member can update a tech stack entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $entry   = TechStack::factory()->create(['project_id' => $project->id]);

    $this->patchJson("/api/v1/projects/{$project->id}/tech-stack/{$entry->id}", [
        'name'    => 'Updated',
        'version' => '2.0',
    ])->assertStatus(200)->assertJsonFragment(['name' => 'Updated']);
});

it('returns 404 when updating a tech stack entry from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $entry    = TechStack::factory()->create(['project_id' => $project2->id]);

    $this->patchJson("/api/v1/projects/{$project->id}/tech-stack/{$entry->id}", ['name' => 'X'])
         ->assertStatus(404);
});

// --- Destroy ---

it('member can delete a tech stack entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $entry   = TechStack::factory()->create(['project_id' => $project->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/tech-stack/{$entry->id}")
         ->assertStatus(200);

    expect(TechStack::find($entry->id))->toBeNull();
});

it('returns 404 when deleting a tech stack entry from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $entry    = TechStack::factory()->create(['project_id' => $project2->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/tech-stack/{$entry->id}")
         ->assertStatus(404);
});
