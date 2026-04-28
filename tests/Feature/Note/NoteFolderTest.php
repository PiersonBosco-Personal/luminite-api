<?php

use App\Models\NoteFolder;
use App\Models\User;

// --- Index ---

it('returns note folders ordered by position', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'name' => 'B', 'position' => 1]);
    NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'name' => 'A', 'position' => 0]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/note-folders")
        ->assertStatus(200)
        ->json('data');

    expect($data[0]['name'])->toBe('A')
        ->and($data[1]['name'])->toBe('B');
});

it('returns 403 on note folder index when not a member', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/note-folders")->assertStatus(403);
});

// --- Store ---

it('member can create a note folder', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name' => 'Design Notes',
    ])->assertStatus(201)->assertJsonFragment(['name' => 'Design Notes']);
});

it('sets created_by to the acting user on folder create', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name' => 'My Folder',
    ])->assertStatus(201);

    expect(NoteFolder::first()->created_by)->toBe($user->id);
});

it('auto-increments position when not provided on folder create', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'position' => 0]);
    NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'position' => 1]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name' => 'Third',
    ])->assertStatus(201);

    expect($response->json('data.position'))->toBe(2);
});

it('returns 422 when name is missing on folder store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/note-folders", [])
        ->assertStatus(422);
});

// --- Update ---

it('member can rename a note folder', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $folder  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}", [
        'name' => 'Renamed',
    ])->assertStatus(200)->assertJsonFragment(['name' => 'Renamed']);
});

it('returns 404 when updating a folder from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $folder   = NoteFolder::factory()->create(['project_id' => $project2->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}", [
        'name' => 'X',
    ])->assertStatus(404);
});

// --- Destroy ---

it('member can delete a note folder', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $folder  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}")
        ->assertStatus(200);

    expect(NoteFolder::find($folder->id))->toBeNull();
});

it('returns 404 when deleting a folder from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $folder   = NoteFolder::factory()->create(['project_id' => $project2->id, 'created_by' => $user->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}")
        ->assertStatus(404);
});

// --- Sub-folders ---

it('creates a sub-folder inside an existing root folder', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $parent  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name'      => 'Sub',
        'parent_id' => $parent->id,
    ])->assertStatus(201);

    expect($response->json('data.parent_id'))->toBe($parent->id);
});

it('returns 422 when parent_id itself has a parent (one level deep only)', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $root    = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $sub     = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'parent_id' => $root->id]);

    $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name'      => 'Grandchild',
        'parent_id' => $sub->id,
    ])->assertStatus(422);
});

it('returns 422 when parent_id belongs to a different project', function () {
    $user        = actingAsUser();
    $project     = createProject($user);
    $other       = User::factory()->create();
    $project2    = createProject($other);
    $otherFolder = NoteFolder::factory()->create(['project_id' => $project2->id, 'created_by' => $other->id]);

    $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name'      => 'Sub',
        'parent_id' => $otherFolder->id,
    ])->assertStatus(422);
});

it('auto-increments position scoped to parent on sub-folder create', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $parent  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'parent_id' => $parent->id, 'position' => 0]);
    NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'parent_id' => $parent->id, 'position' => 1]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name'      => 'Third Sub',
        'parent_id' => $parent->id,
    ])->assertStatus(201);

    expect($response->json('data.position'))->toBe(2);
});

it('cascade deletes sub-folders when the parent folder is deleted', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $parent  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $child   = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'parent_id' => $parent->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/note-folders/{$parent->id}")
        ->assertStatus(200);

    expect(NoteFolder::find($child->id))->toBeNull();
});

it('can move a root folder into another folder by updating parent_id', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $parent  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $folder  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->putJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}", [
        'parent_id' => $parent->id,
    ])->assertStatus(200);

    expect($response->json('data.parent_id'))->toBe($parent->id);
});

it('returns 422 when updating parent_id to a folder that itself has a parent', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $root    = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $sub     = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'parent_id' => $root->id]);
    $folder  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}", [
        'parent_id' => $sub->id,
    ])->assertStatus(422);
});
