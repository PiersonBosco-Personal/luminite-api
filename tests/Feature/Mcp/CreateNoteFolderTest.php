<?php

use App\Models\NoteFolder;
use App\Models\Task;
use App\Models\TaskSection;

function createNoteViaMcp($test, string $raw, array $args, int $id = 1): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => $id,
            'params' => ['name' => 'create_note', 'arguments' => $args],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('places a new MCP note into a root-level Claude folder', function () {
    [$raw, $token, $project] = mcpToken([], ['read', 'write']);

    createNoteViaMcp($this, $raw, ['title' => 'First note', 'content' => 'hello']);

    $folder = NoteFolder::where('project_id', $project->id)->where('name', 'Claude')->first();
    expect($folder)->not->toBeNull()
        ->and($folder->parent_id)->toBeNull()
        ->and($folder->created_by)->toBe($token->user_id);

    $note = $project->notes()->where('title', 'First note')->first();
    expect($note->folder_id)->toBe($folder->id);
});

it('reuses the same Claude folder for a second note', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    createNoteViaMcp($this, $raw, ['title' => 'Note A'], 1);
    createNoteViaMcp($this, $raw, ['title' => 'Note B'], 2);

    expect(NoteFolder::where('project_id', $project->id)->where('name', 'Claude')->count())->toBe(1);
});

it('reuses a pre-existing Claude folder created by a human', function () {
    [$raw, , $project, $user] = mcpToken([], ['read', 'write']);
    $existing = NoteFolder::create([
        'project_id' => $project->id,
        'parent_id' => null,
        'created_by' => $user->id,
        'name' => 'Claude',
        'position' => 0,
    ]);

    createNoteViaMcp($this, $raw, ['title' => 'Note C']);

    expect(NoteFolder::where('project_id', $project->id)->where('name', 'Claude')->count())->toBe(1);
    expect($project->notes()->where('title', 'Note C')->first()->folder_id)->toBe($existing->id);
});

it('folders a task-linked note into the Claude folder and keeps the task link', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);
    $task = Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'A task', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    createNoteViaMcp($this, $raw, ['title' => 'Linked note', 'task_id' => $task->id]);

    $note = $project->notes()->where('title', 'Linked note')->first();
    $folder = NoteFolder::where('project_id', $project->id)->where('name', 'Claude')->first();
    expect($note->folder_id)->toBe($folder->id)
        ->and($note->task_id)->toBe($task->id);
});
