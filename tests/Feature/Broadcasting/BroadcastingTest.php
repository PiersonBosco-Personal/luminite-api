<?php

use App\Events\LabelCreated;
use App\Events\LabelDeleted;
use App\Events\LabelUpdated;
use App\Events\NoteCreated;
use App\Events\NoteDeleted;
use App\Events\NoteFolderCreated;
use App\Events\NoteFolderDeleted;
use App\Events\NoteFolderUpdated;
use App\Events\NoteUpdated;
use App\Events\SectionCreated;
use App\Events\SectionDeleted;
use App\Events\SectionUpdated;
use App\Events\SectionsReordered;
use App\Events\TaskCreated;
use App\Events\TaskDeleted;
use App\Events\TasksReordered;
use App\Events\TaskUpdated;
use App\Models\Label;
use App\Models\Note;
use App\Models\NoteFolder;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;
use Illuminate\Support\Facades\Event;

// --- Task Events ---

it('broadcasts TaskCreated when a task is created', function () {
    Event::fake([TaskCreated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->postJson("/api/v1/projects/{$project->id}/tasks", [
        'title'      => 'New Task',
        'section_id' => $section->id,
    ])->assertStatus(201);

    Event::assertDispatched(TaskCreated::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts TaskUpdated when a task is updated', function () {
    Event::fake([TaskUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}", [
        'title' => 'Updated',
    ])->assertStatus(200);

    Event::assertDispatched(TaskUpdated::class, fn ($e) => $e->task->id === $task->id);
});

it('broadcasts TaskDeleted when a task is deleted', function () {
    Event::fake([TaskDeleted::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/tasks/{$task->id}")
        ->assertStatus(200);

    Event::assertDispatched(TaskDeleted::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts TasksReordered when tasks are reordered', function () {
    Event::fake([TasksReordered::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);
    $task    = Task::factory()->create(['project_id' => $project->id, 'section_id' => $section->id, 'position' => 0]);

    $this->postJson("/api/v1/projects/{$project->id}/tasks/reorder", [
        'tasks' => [['id' => $task->id, 'section_id' => $section->id, 'position' => 0]],
    ])->assertStatus(200);

    Event::assertDispatched(TasksReordered::class, fn ($e) => $e->projectId === $project->id);
});

// --- Section Events ---

it('broadcasts SectionCreated when a section is created', function () {
    Event::fake([SectionCreated::class]);

    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/sections", [
        'name' => 'Backlog',
    ])->assertStatus(201);

    Event::assertDispatched(SectionCreated::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts SectionUpdated when a section is updated', function () {
    Event::fake([SectionUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->putJson("/api/v1/projects/{$project->id}/sections/{$section->id}", [
        'name' => 'In Progress',
    ])->assertStatus(200);

    Event::assertDispatched(SectionUpdated::class, fn ($e) => $e->section->id === $section->id);
});

it('broadcasts SectionDeleted when a section is deleted', function () {
    Event::fake([SectionDeleted::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/sections/{$section->id}")
        ->assertStatus(200);

    Event::assertDispatched(SectionDeleted::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts SectionsReordered when sections are reordered', function () {
    Event::fake([SectionsReordered::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    $this->postJson("/api/v1/projects/{$project->id}/sections/reorder", [
        'sections' => [['id' => $section->id, 'position' => 0]],
    ])->assertStatus(200);

    Event::assertDispatched(SectionsReordered::class, fn ($e) => $e->projectId === $project->id);
});

// --- Note Events ---

it('broadcasts NoteCreated when a note is created', function () {
    Event::fake([NoteCreated::class]);

    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/notes", [
        'title' => 'Meeting Notes',
    ])->assertStatus(201);

    Event::assertDispatched(NoteCreated::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts NoteUpdated when a note is updated', function () {
    Event::fake([NoteUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/notes/{$note->id}", [
        'title' => 'Updated Title',
    ])->assertStatus(200);

    Event::assertDispatched(NoteUpdated::class, fn ($e) => $e->note->id === $note->id);
});

it('broadcasts NoteDeleted when a note is deleted', function () {
    Event::fake([NoteDeleted::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/notes/{$note->id}")
        ->assertStatus(200);

    Event::assertDispatched(NoteDeleted::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts NoteUpdated when a note pin is toggled', function () {
    Event::fake([NoteUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $note    = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'is_pinned' => false]);

    $this->patchJson("/api/v1/projects/{$project->id}/notes/{$note->id}/pin")
        ->assertStatus(200);

    Event::assertDispatched(NoteUpdated::class, fn ($e) => $e->note->id === $note->id);
});

// --- Note Folder Events ---

it('broadcasts NoteFolderCreated when a folder is created', function () {
    Event::fake([NoteFolderCreated::class]);

    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/note-folders", [
        'name' => 'Archive',
    ])->assertStatus(201);

    Event::assertDispatched(NoteFolderCreated::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts NoteFolderUpdated when a folder is updated', function () {
    Event::fake([NoteFolderUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $folder  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->putJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}", [
        'name' => 'Renamed',
    ])->assertStatus(200);

    Event::assertDispatched(NoteFolderUpdated::class, fn ($e) => $e->folder->id === $folder->id);
});

it('broadcasts NoteFolderDeleted when a folder is deleted', function () {
    Event::fake([NoteFolderDeleted::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $folder  = NoteFolder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/note-folders/{$folder->id}")
        ->assertStatus(200);

    Event::assertDispatched(NoteFolderDeleted::class, fn ($e) => $e->projectId === $project->id);
});

// --- Label Events ---

it('broadcasts LabelCreated when a label is created', function () {
    Event::fake([LabelCreated::class]);

    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/labels", [
        'name'  => 'Bug',
        'color' => '#ff0000',
    ])->assertStatus(201);

    Event::assertDispatched(LabelCreated::class, fn ($e) => $e->projectId === $project->id);
});

it('broadcasts LabelUpdated when a label is updated', function () {
    Event::fake([LabelUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $label   = Label::factory()->create(['project_id' => $project->id]);

    $this->putJson("/api/v1/projects/{$project->id}/labels/{$label->id}", [
        'name' => 'Feature',
    ])->assertStatus(200);

    Event::assertDispatched(LabelUpdated::class, fn ($e) => $e->label->id === $label->id);
});

it('broadcasts LabelDeleted when a label is deleted', function () {
    Event::fake([LabelDeleted::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    $label   = Label::factory()->create(['project_id' => $project->id]);

    $this->deleteJson("/api/v1/projects/{$project->id}/labels/{$label->id}")
        ->assertStatus(200);

    Event::assertDispatched(LabelDeleted::class, fn ($e) => $e->projectId === $project->id);
});

// --- Channel Authorization ---
// NullBroadcaster (used in tests) bypasses auth() entirely, so we invoke the
// registered channel callbacks directly via reflection rather than HTTP.

function channelCallback(string $pattern): callable
{
    $broadcaster = app(\Illuminate\Broadcasting\BroadcastManager::class)->driver();
    $channels    = (new \ReflectionProperty($broadcaster, 'channels'))->getValue($broadcaster);

    return $channels[$pattern] ?? fn () => false;
}

it('project member is authorized on the project channel', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    $result = channelCallback('project.{projectId}')($user, $project->id);

    expect($result)->toBeTrue();
});

it('non-member is denied on the project channel', function () {
    $user    = User::factory()->create();
    $other   = User::factory()->create();
    $project = createProject($other);

    $result = channelCallback('project.{projectId}')($user, $project->id);

    expect($result)->toBeFalse();
});

it('presence channel returns user identity for a project member', function () {
    $user    = User::factory()->create();
    $project = createProject($user);

    $result = channelCallback('presence-project.{projectId}')($user, $project->id);

    expect($result)->toMatchArray(['id' => $user->id, 'name' => $user->name]);
});

it('non-member is denied on the presence project channel', function () {
    $user    = User::factory()->create();
    $other   = User::factory()->create();
    $project = createProject($other);

    $result = channelCallback('presence-project.{projectId}')($user, $project->id);

    expect($result)->toBeFalse();
});
