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

    // First sync creates both.
    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => 1,
        'params'  => ['name' => 'sync_todos', 'arguments' => [
            'files' => ['auth.php', 'tasks.php'],
            'todos' => [
                ['text' => 'Fix login race', 'file' => 'auth.php', 'line' => 10],
                ['text' => 'Add tests', 'file' => 'tasks.php', 'line' => 5],
            ],
        ]],
    ])->assertStatus(200);

    expect(Task::where('project_id', $project->id)->count())->toBe(2);

    // Second sync: same two todos are skipped, one new is created.
    $text = $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => 2,
        'params'  => ['name' => 'sync_todos', 'arguments' => [
            'files' => ['auth.php', 'tasks.php', 'new.php'],
            'todos' => [
                ['text' => 'Fix login race', 'file' => 'auth.php', 'line' => 10],
                ['text' => 'Add tests', 'file' => 'tasks.php', 'line' => 5],
                ['text' => 'New thing', 'file' => 'new.php', 'line' => 1],
            ],
        ]],
    ])->assertStatus(200)->json('result.content.0.text');

    expect(Task::where('project_id', $project->id)->count())->toBe(3)
        ->and($text)->toContain('created 1')
        ->and($text)->toContain('skipped 2');
});

it('sync_todos returns an error when files is empty', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $text = $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => 3,
        'params'  => ['name' => 'sync_todos', 'arguments' => ['files' => [], 'todos' => []]],
    ])->assertStatus(200)->json('result.content.0.text');

    expect($text)->toContain('Error');
});

it('syncs new todos into a Triage section and stamps the source file', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 15,
            'params'  => ['name' => 'sync_todos', 'arguments' => [
                'files' => ['src/auth.ts'],
                'todos' => [['text' => 'handle refresh token', 'file' => 'src/auth.ts', 'line' => 10]],
            ]],
        ])->assertJsonPath('result.content.0.text', fn ($t) => str_contains($t, 'created 1'));

    $task = \App\Models\Task::where('project_id', $project->id)->where('title', 'handle refresh token')->first();
    expect($task)->not->toBeNull()->and($task->source_file)->toBe('src/auth.ts');
    expect(\App\Models\TaskSection::where('project_id', $project->id)->whereRaw('LOWER(name) = ?', ['triage'])->exists())->toBeTrue();
});

it('auto-completes a tracked todo that has vanished from a scanned file', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    // First sync: TODO exists.
    $args = fn (array $todos) => [
        'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 16,
        'params'  => ['name' => 'sync_todos', 'arguments' => [
            'files' => ['src/auth.ts'], 'todos' => $todos,
        ]],
    ];
    $this->withToken($raw)->postJson('/api/mcp', $args([['text' => 'do thing', 'file' => 'src/auth.ts']]));
    $task = \App\Models\Task::where('title', 'do thing')->first();
    expect($task->status)->not->toBe('done');

    // Second sync: same file scanned, TODO is gone.
    $this->withToken($raw)
        ->postJson('/api/mcp', $args([]))
        ->assertJsonPath('result.content.0.text', fn ($t) => str_contains($t, 'completed 1'));

    expect($task->fresh()->status)->toBe('done');
});

it('sync_todos honours the priority field on incoming todos', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $this->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => 20,
        'params'  => ['name' => 'sync_todos', 'arguments' => [
            'files' => ['src/x.ts', 'src/y.ts'],
            'todos' => [
                ['text' => 'urgent fix', 'file' => 'src/x.ts', 'priority' => 'high'],
                ['text' => 'normal task', 'file' => 'src/y.ts'],
            ],
        ]],
    ])->assertStatus(200);

    $highTask   = Task::where('project_id', $project->id)->where('title', 'urgent fix')->first();
    $mediumTask = Task::where('project_id', $project->id)->where('title', 'normal task')->first();

    expect($highTask)->not->toBeNull()
        ->and($highTask->priority)->toBe('high');

    expect($mediumTask)->not->toBeNull()
        ->and($mediumTask->priority)->toBe('medium');
});

it('todo moving files auto-completes old task and creates a new one', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $call = fn (array $files, array $todos, int $id) => [
        'jsonrpc' => '2.0',
        'method'  => 'tools/call',
        'id'      => $id,
        'params'  => ['name' => 'sync_todos', 'arguments' => compact('files', 'todos')],
    ];

    // First sync: todo lives in a.ts.
    $this->withToken($raw)->postJson('/api/mcp', $call(
        ['a.ts'],
        [['text' => 'shared todo', 'file' => 'a.ts']],
        30,
    ))->assertStatus(200);

    $old = Task::where('project_id', $project->id)->where('title', 'shared todo')->first();
    expect($old)->not->toBeNull()->and($old->status)->not->toBe('done');

    // Second sync: both files scanned, but the todo now lives in b.ts.
    $this->withToken($raw)->postJson('/api/mcp', $call(
        ['a.ts', 'b.ts'],
        [['text' => 'shared todo', 'file' => 'b.ts']],
        31,
    ))->assertStatus(200);

    // Old task (source_file = a.ts) should be auto-completed.
    expect($old->fresh()->status)->toBe('done');

    // A brand-new task should exist for the todo in b.ts.
    $newTask = Task::where('project_id', $project->id)
        ->where('title', 'shared todo')
        ->where('source_file', 'b.ts')
        ->first();
    expect($newTask)->not->toBeNull()
        ->and($newTask->status)->not->toBe('done');

    // Exactly two tasks named 'shared todo' in this project (one done, one open).
    expect(Task::where('project_id', $project->id)->where('title', 'shared todo')->count())->toBe(2);
});
