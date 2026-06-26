<?php

use App\Models\DashboardWidget;
use App\Models\Label;
use App\Models\Note;
use App\Models\NoteFolder;
use App\Models\Task;
use App\Models\TaskSection;

beforeEach(function () {
    $this->seed(\Database\Seeders\WidgetSeeder::class);
});

function callInit($test, string $raw, array $args, int $id = 1)
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => $id,
            'params' => ['name' => 'initialize_project', 'arguments' => $args],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}

it('refuses a non-blank project without confirm and writes nothing', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Old', 'position' => 0]);
    $project->update(['description' => null]); // isolate: only the section makes it non-blank

    $text = callInit($this, $raw, ['details' => ['description' => 'New desc']]);

    expect($text)->toContain('confirm')
        ->and($text)->toContain('cannot be undone');
    expect($project->fresh()->description)->toBeNull();
    expect(TaskSection::where('project_id', $project->id)->where('name', 'Old')->exists())->toBeTrue();
});

it('overwrites init-managed data on confirm:true and preserves notes', function () {
    [$raw, $token, $project] = mcpToken([], ['read', 'write']);

    $oldSection = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Old', 'position' => 0]);
    Task::create(['project_id' => $project->id, 'section_id' => $oldSection->id, 'title' => 'Old task', 'status' => 'todo', 'priority' => 'medium', 'position' => 0]);
    Label::create(['project_id' => $project->id, 'name' => 'oldlabel', 'color' => '#94a3b8']);
    $project->update(['description' => 'Old desc']);

    $folder = NoteFolder::create(['project_id' => $project->id, 'parent_id' => null, 'created_by' => $token->user_id, 'name' => 'Claude', 'position' => 0]);
    $note = Note::create(['project_id' => $project->id, 'folder_id' => $folder->id, 'created_by' => $token->user_id, 'title' => 'Keepme', 'content' => '{}', 'is_pinned' => false, 'position' => 0]);

    $text = callInit($this, $raw, [
        'details' => ['description' => 'Fresh desc'],
        'sections' => ['Backlog', 'Done'],
        'confirm' => true,
    ]);

    expect($text)->toContain('Initialized project');
    expect($project->fresh()->description)->toBe('Fresh desc');
    expect(Task::where('project_id', $project->id)->where('title', 'Old task')->exists())->toBeFalse();
    expect(Label::where('project_id', $project->id)->where('name', 'oldlabel')->exists())->toBeFalse();
    expect(TaskSection::where('project_id', $project->id)->where('name', 'Old')->exists())->toBeFalse();
    expect(TaskSection::where('project_id', $project->id)->where('name', 'Backlog')->exists())->toBeTrue();
    expect(Note::whereKey($note->id)->exists())->toBeTrue();
    expect(NoteFolder::whereKey($folder->id)->exists())->toBeTrue();
});

it('still initializes a blank project without confirm', function () {
    [$raw, , $project] = mcpToken(['description' => null], ['read', 'write']);

    $text = callInit($this, $raw, ['details' => ['description' => 'Blank start']]);

    expect($text)->toContain('Initialized project');
    expect($project->fresh()->description)->toBe('Blank start');
});
