<?php

use App\Models\Note;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;

// --- Index ---

it('returns notes ordered by position then id', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $second = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'position' => 1]);
    $first  = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'position' => 0]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/notes")
                 ->assertStatus(200)
                 ->json('data');

    expect($data[0]['id'])->toBe($first->id)
        ->and($data[1]['id'])->toBe($second->id);
});

it('returns notes with same position ordered by id ascending', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $note1 = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'position' => 0]);
    $note2 = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'position' => 0]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/notes")
                 ->assertStatus(200)
                 ->json('data');

    expect($data[0]['id'])->toBe($note1->id)
        ->and($data[1]['id'])->toBe($note2->id);
});

it('returns 403 on index when not a member', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/notes")->assertStatus(403);
});

// --- Store ---

it('member can create a note and created_by is set to the acting user', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $response = $this->postJson("/api/v1/projects/{$project->id}/notes", [
        'title' => 'My Note',
    ])->assertStatus(201);

    expect($response->json('data.created_by'))->toBe($user->id);
});

it('can link a note to a task on create', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/notes", [
        'title'   => 'Linked Note',
        'task_id' => $task->id,
    ])->assertStatus(201);

    expect($response->json('data.task_id'))->toBe($task->id);
});

it('returns 422 when title is missing on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/notes", [])->assertStatus(422);
});

// --- Show ---

it('member can view a note', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->getJson("/api/v1/projects/{$project->id}/notes/{$note->id}")
         ->assertStatus(200)
         ->assertJsonFragment(['id' => $note->id]);
});

it('returns 404 when note belongs to another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $note     = Note::factory()->create(['project_id' => $project2->id, 'created_by' => $user->id]);

    $this->getJson("/api/v1/projects/{$project->id}/notes/{$note->id}")
         ->assertStatus(404);
});

// --- Update ---

it('member can update a note', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/notes/{$note->id}", [
        'title' => 'Updated Title',
    ])->assertStatus(200)->assertJsonFragment(['title' => 'Updated Title']);
});

it('returns 404 when updating a note from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $note     = Note::factory()->create(['project_id' => $project2->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/notes/{$note->id}", ['title' => 'X'])
         ->assertStatus(404);
});

// --- Destroy ---

it('member can delete a note', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/notes/{$note->id}")
         ->assertStatus(200);

    expect(Note::find($note->id))->toBeNull();
});

// --- Toggle Pin ---

it('toggles is_pinned on a note', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'is_pinned' => false]);

    $this->patchJson("/api/v1/projects/{$project->id}/notes/{$note->id}/pin")
         ->assertStatus(200);

    expect($note->fresh()->is_pinned)->toBeTrue();

    $this->patchJson("/api/v1/projects/{$project->id}/notes/{$note->id}/pin")
         ->assertStatus(200);

    expect($note->fresh()->is_pinned)->toBeFalse();
});

it('returns 404 when toggling pin on a note from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $note     = Note::factory()->create(['project_id' => $project2->id, 'created_by' => $user->id]);

    $this->patchJson("/api/v1/projects/{$project->id}/notes/{$note->id}/pin")
         ->assertStatus(404);
});
