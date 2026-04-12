<?php

use App\Models\Label;
use App\Models\Note;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;

// --- Index ---

it('member can list labels', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    Label::factory()->count(3)->create(['project_id' => $project->id]);

    $this->getJson("/api/v1/projects/{$project->id}/labels")
         ->assertStatus(200)
         ->assertJsonCount(3, 'data');
});

it('returns 403 on index when not a member', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/labels")->assertStatus(403);
});

// --- Store ---

it('member can create a label', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/labels", [
        'name'  => 'Bug',
        'color' => '#ef4444',
    ])->assertStatus(201)->assertJsonFragment(['name' => 'Bug']);
});

it('returns 422 when name is missing on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/labels", ['color' => '#ef4444'])
         ->assertStatus(422);
});

it('returns 422 when color is not a valid hex on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/labels", [
        'name'  => 'Bug',
        'color' => 'red',
    ])->assertStatus(422);
});

// --- Update ---

it('member can update a label', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $label   = Label::factory()->create(['project_id' => $project->id]);

    $this->putJson("/api/v1/projects/{$project->id}/labels/{$label->id}", [
        'name'  => 'Updated',
        'color' => '#000000',
    ])->assertStatus(200)->assertJsonFragment(['name' => 'Updated']);
});

it('returns 404 when updating a label from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $label    = Label::factory()->create(['project_id' => $project2->id]);

    $this->putJson("/api/v1/projects/{$project->id}/labels/{$label->id}", ['name' => 'X'])
         ->assertStatus(404);
});

// --- Destroy ---

it('member can delete a label', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $label   = Label::factory()->create(['project_id' => $project->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/labels/{$label->id}")
         ->assertStatus(200);

    expect(Label::find($label->id))->toBeNull();
});

it('returns 404 when deleting a label from another project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $label    = Label::factory()->create(['project_id' => $project2->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/labels/{$label->id}")
         ->assertStatus(404);
});

// --- Attach to Task ---

it('member can attach a label to a task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);
    $label   = Label::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/labels/{$label->id}/tasks/attach", [
        'task_id' => $task->id,
    ])->assertStatus(200);

    expect($task->labels()->where('label_id', $label->id)->exists())->toBeTrue();
});

it('attaching a label to a task twice is idempotent', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);
    $label   = Label::factory()->create(['project_id' => $project->id]);
    $task->labels()->attach($label->id);

    $this->postJson("/api/v1/projects/{$project->id}/labels/{$label->id}/tasks/attach", [
        'task_id' => $task->id,
    ])->assertStatus(200);

    expect($task->labels()->where('label_id', $label->id)->count())->toBe(1);
});

it('returns 422 when task_id is missing on attach to task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $label   = Label::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/labels/{$label->id}/tasks/attach", [])
         ->assertStatus(422);
});

it('returns 422 when task_id does not belong to the project on attach', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $other    = User::factory()->create();
    $project2 = createProject($other);
    addMemberToProject($project2, $user);
    $section  = TaskSection::factory()->create(['project_id' => $project2->id]);
    $task     = Task::factory()->create(['project_id' => $project2->id, 'section_id' => $section->id]);
    $label    = Label::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/labels/{$label->id}/tasks/attach", [
        'task_id' => $task->id,
    ])->assertStatus(422);
});

// --- Detach from Task ---

it('member can detach a label from a task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);
    $label   = Label::factory()->create(['project_id' => $project->id]);
    $task->labels()->attach($label->id);

    $this->deleteJson("/api/v1/projects/{$project->id}/labels/{$label->id}/tasks/detach", [
        'task_id' => $task->id,
    ])->assertStatus(200);

    expect($task->labels()->where('label_id', $label->id)->exists())->toBeFalse();
});

// --- Attach to Note ---

it('member can attach a label to a note', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $label   = Label::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/labels/{$label->id}/notes/attach", [
        'note_id' => $note->id,
    ])->assertStatus(200);

    expect($note->labels()->where('label_id', $label->id)->exists())->toBeTrue();
});

// --- Detach from Note ---

it('member can detach a label from a note', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $label   = Label::factory()->create(['project_id' => $project->id]);
    $note->labels()->attach($label->id);

    $this->deleteJson("/api/v1/projects/{$project->id}/labels/{$label->id}/notes/detach", [
        'note_id' => $note->id,
    ])->assertStatus(200);

    expect($note->labels()->where('label_id', $label->id)->exists())->toBeFalse();
});
