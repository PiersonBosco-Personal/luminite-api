<?php

use App\Models\Task;
use App\Models\TaskSection;
use App\Models\User;
use Illuminate\Database\QueryException;

it('rejects a duplicate source_hash within the same project', function () {
    $user    = User::factory()->create();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    Task::factory()->create([
        'project_id'  => $project->id,
        'section_id'  => $section->id,
        'title'       => 'TODO: fix login',
        'source_hash' => 'abc123',
    ]);

    expect(fn () => Task::factory()->create([
        'project_id'  => $project->id,
        'section_id'  => $section->id,
        'title'       => 'TODO: fix login again',
        'source_hash' => 'abc123',
    ]))->toThrow(QueryException::class);
});

it('sync_todos creates new tasks and skips already-tracked ones', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0]);

    // First sync creates both.
    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => 1,
        'params'  => ['name' => 'sync_todos', 'arguments' => ['todos' => [
            ['text' => 'Fix login race', 'file' => 'auth.php', 'line' => 10],
            ['text' => 'Add tests', 'file' => 'tasks.php', 'line' => 5],
        ]]],
    ])->assertStatus(200);

    expect(Task::where('project_id', $project->id)->count())->toBe(2);

    // Second sync: same two todos are skipped, one new is created.
    $text = $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => 2,
        'params'  => ['name' => 'sync_todos', 'arguments' => ['todos' => [
            ['text' => 'Fix login race', 'file' => 'auth.php', 'line' => 10],
            ['text' => 'Add tests', 'file' => 'tasks.php', 'line' => 5],
            ['text' => 'New thing', 'file' => 'new.php', 'line' => 1],
        ]]],
    ])->assertStatus(200)->json('result.content.0.text');

    expect(Task::where('project_id', $project->id)->count())->toBe(3)
        ->and($text)->toContain('created 1')
        ->and($text)->toContain('skipped 2');
});

it('sync_todos returns an error for an empty list', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    TaskSection::factory()->create(['project_id' => $project->id, 'position' => 0]);

    $text = $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => 3,
        'params'  => ['name' => 'sync_todos', 'arguments' => ['todos' => []]],
    ])->assertStatus(200)->json('result.content.0.text');

    expect($text)->toContain('Error');
});
