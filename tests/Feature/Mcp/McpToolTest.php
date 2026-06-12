<?php

use App\Models\McpToken;
use App\Models\TechStack;
use App\Models\User;

it('echoes the client-requested protocol version on initialize', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'initialize',
             'id'      => 1,
             'params'  => ['protocolVersion' => '2025-06-18'],
         ])
         ->assertStatus(200)
         ->assertJsonPath('jsonrpc', '2.0')
         ->assertJsonPath('result.serverInfo.name', 'luminite')
         ->assertJsonPath('result.protocolVersion', '2025-06-18');
});

it('falls back to the default protocol version when the client sends none', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 1])
         ->assertStatus(200)
         ->assertJsonPath('jsonrpc', '2.0')
         ->assertJsonPath('result.protocolVersion', '2025-06-18');
});

it('lists available tools including get_session_context', function () {
    [$raw] = mcpToken();

    $data = $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2])
         ->assertStatus(200)
         ->json('result.tools');

    expect(collect($data)->pluck('name'))
        ->toContain('get_session_context')
        ->not->toContain('get_project_context');
});

it('returns project name, description, and status in context', function () {
    [$raw] = mcpToken([
        'name'        => 'My App',
        'description' => 'A web platform',
        'status'      => 'active',
    ]);

    $text = $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'tools/call',
             'id'      => 3,
             'params'  => ['name' => 'get_session_context', 'arguments' => []],
         ])
         ->assertStatus(200)
         ->json('result.content.0.text');

    expect($text)->toContain('My App')
        ->and($text)->toContain('A web platform')
        ->and($text)->toContain('active');
});

it('returns tech stack entries in context', function () {
    $user    = User::factory()->create();
    $project = createProject($user, ['name' => 'Stack Test']);
    $root    = TechStack::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Laravel',
        'version'    => '11',
        'parent_id'  => null,
    ]);
    TechStack::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Sanctum',
        'version'    => null,
        'parent_id'  => $root->id,
    ]);
    [, $raw] = McpToken::generate($user, $project, 'test', ['read']);

    $text = $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'tools/call',
             'id'      => 4,
             'params'  => ['name' => 'get_session_context', 'arguments' => []],
         ])
         ->assertStatus(200)
         ->json('result.content.0.text');

    expect($text)->toContain('Laravel (11)')
        ->and($text)->toContain('Sanctum');
});

it('returns error for unknown method', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'unknown/method', 'id' => 5])
         ->assertStatus(200)
         ->assertJsonPath('error.code', -32601);
});

it('returns error for unknown tool name in tools/call', function () {
    [$raw] = mcpToken();

    $this->withToken($raw)
         ->postJson('/api/mcp', [
             'jsonrpc' => '2.0',
             'method'  => 'tools/call',
             'id'      => 6,
             'params'  => ['name' => 'nonexistent_tool', 'arguments' => []],
         ])
         ->assertStatus(200)
         ->assertJsonPath('error.code', -32601);
});

it('get_labels returns label names for the project', function () {
    [$raw, , $project] = mcpToken();

    \App\Models\Label::factory()->create([
        'project_id' => $project->id,
        'name'       => 'frontend',
        'color'      => '#3b82f6',
    ]);
    \App\Models\Label::factory()->create([
        'project_id' => $project->id,
        'name'       => 'bug',
        'color'      => '#ef4444',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 10,
            'params'  => ['name' => 'get_labels', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('frontend')
        ->and($text)->toContain('bug');
});

it('get_labels returns no-labels message when project has none', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 11,
            'params'  => ['name' => 'get_labels', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No labels');
});

it('get_labels does not return labels from other projects', function () {
    [$raw] = mcpToken();

    $otherUser    = \App\Models\User::factory()->create();
    $otherProject = createProject($otherUser);
    \App\Models\Label::factory()->create([
        'project_id' => $otherProject->id,
        'name'       => 'secret-label',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 12,
            'params'  => ['name' => 'get_labels', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('secret-label');
});

it('get_sections returns section names in order', function () {
    [$raw, , $project] = mcpToken();

    \App\Models\TaskSection::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Backlog',
        'position'   => 0,
    ]);
    \App\Models\TaskSection::factory()->create([
        'project_id' => $project->id,
        'name'       => 'In Progress',
        'position'   => 1,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 20,
            'params'  => ['name' => 'get_sections', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Backlog')
        ->and($text)->toContain('In Progress');
});

it('get_sections returns no-sections message when project has none', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 21,
            'params'  => ['name' => 'get_sections', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No sections');
});

it('get_sections does not return sections from other projects', function () {
    [$raw] = mcpToken();

    $other = createProject(\App\Models\User::factory()->create());
    \App\Models\TaskSection::factory()->create([
        'project_id' => $other->id,
        'name'       => 'secret-section',
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 22,
            'params'  => ['name' => 'get_sections', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('secret-section');
});

it('caps open tasks in session context and notes the overflow', function () {
    [$raw, , $project] = mcpToken();
    $section = \App\Models\TaskSection::create([
        'project_id' => $project->id, 'name' => 'Backlog', 'position' => 0,
    ]);

    foreach (range(1, 30) as $n) {
        \App\Models\Task::create([
            'project_id' => $project->id, 'section_id' => $section->id,
            'title' => "Task {$n}", 'status' => 'todo', 'priority' => 'medium', 'position' => $n,
        ]);
    }

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 3,
            'params'  => ['name' => 'get_session_context', 'arguments' => []],
        ])
        ->json('result.content.0.text');

    expect($text)->toContain('Open Tasks (30)')
        ->and($text)->toContain('… +5 more (use get_open_tasks to see all)');
});

it('annotates read tools as read-only and write tools as not read-only', function () {
    [$raw] = mcpToken([], ['read', 'write']);

    $tools = collect($this->withToken($raw)
        ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2])
        ->json('result.tools'))
        ->keyBy('name');

    expect($tools['get_open_tasks']['annotations']['readOnlyHint'])->toBeTrue()
        ->and($tools['create_task']['annotations']['readOnlyHint'])->toBeFalse()
        ->and($tools['create_task']['annotations']['destructiveHint'])->toBeFalse();
});

it('updates a task title, priority, and moves it to another section', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $todo = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'Todo', 'position' => 0]);
    $doing = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'In Progress', 'position' => 1]);
    $task = \App\Models\Task::create([
        'project_id' => $project->id, 'section_id' => $todo->id,
        'title' => 'Old title', 'status' => 'todo', 'priority' => 'low', 'position' => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 8,
            'params'  => ['name' => 'update_task', 'arguments' => [
                'task_id'  => $task->id,
                'title'    => 'New title',
                'priority' => 'high',
                'section'  => 'In Progress',
            ]],
        ])
        ->json('result.content.0.text');

    $task->refresh();
    expect($task->title)->toBe('New title')
        ->and($task->priority)->toBe('high')
        ->and($task->section_id)->toBe($doing->id);
    expect($text)->toContain("Updated task #{$task->id}");
});

it('rejects update_task without the write scope', function () {
    [$raw, , $project] = mcpToken([], ['read']); // read-only
    $section = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'Todo', 'position' => 0]);
    $task = \App\Models\Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'X', 'status' => 'todo', 'priority' => 'low', 'position' => 0,
    ]);

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 8,
            'params'  => ['name' => 'update_task', 'arguments' => ['task_id' => $task->id, 'title' => 'Y']],
        ])
        ->assertJsonPath('error.code', -32603);
});

it('rejects an assignee who is not a project member', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'Todo', 'position' => 0]);
    $task = \App\Models\Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'Some task', 'status' => 'todo', 'priority' => 'low', 'position' => 0,
    ]);

    $outsider = \App\Models\User::factory()->create();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 9,
            'params'  => ['name' => 'update_task', 'arguments' => [
                'task_id'     => $task->id,
                'assignee_id' => $outsider->id,
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('is not a member of this project');
    expect($task->fresh()->assigned_to)->toBeNull();
});

it('rejects a parent task from a different project', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'Todo', 'position' => 0]);
    $task = \App\Models\Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'My task', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $otherUser    = \App\Models\User::factory()->create();
    $otherProject = createProject($otherUser);
    $otherSection = \App\Models\TaskSection::factory()->create(['project_id' => $otherProject->id, 'position' => 0]);
    $foreignTask  = \App\Models\Task::create([
        'project_id' => $otherProject->id, 'section_id' => $otherSection->id,
        'title' => 'Foreign task', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 10,
            'params'  => ['name' => 'update_task', 'arguments' => [
                'task_id' => $task->id,
                'parent'  => $foreignTask->id,
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('not found in this project');
    expect($task->fresh()->parent_task_id)->toBeNull();
});

it('replaces a task\'s labels via update_task', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'Todo', 'position' => 0]);
    $task = \App\Models\Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'Labelled task', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $labelA = \App\Models\Label::create(['project_id' => $project->id, 'name' => 'frontend', 'color' => '#111111']);
    $labelB = \App\Models\Label::create(['project_id' => $project->id, 'name' => 'backend',  'color' => '#222222']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 11,
            'params'  => ['name' => 'update_task', 'arguments' => [
                'task_id' => $task->id,
                'labels'  => [$labelA->id, $labelB->id],
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain("Updated task #{$task->id}");

    $attachedIds = $task->fresh()->labels->pluck('id')->sort()->values()->all();
    expect($attachedIds)->toHaveCount(2)
        ->and($attachedIds)->toContain($labelA->id)
        ->and($attachedIds)->toContain($labelB->id);
});

it('creates a note linked to a task', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $section = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'Todo', 'position' => 0]);
    $task = \App\Models\Task::create([
        'project_id' => $project->id, 'section_id' => $section->id,
        'title' => 'Auth', 'status' => 'todo', 'priority' => 'medium', 'position' => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 10,
            'params'  => ['name' => 'create_note', 'arguments' => [
                'title'   => 'Auth decision',
                'content' => "Chose Sanctum.\nPermanent tokens.",
                'task_id' => $task->id,
            ]],
        ])
        ->json('result.content.0.text');

    $note = \App\Models\Note::where('title', 'Auth decision')->first();
    expect($note)->not->toBeNull()
        ->and($note->task_id)->toBe($task->id)
        ->and($note->content)->toContain('Chose Sanctum');
    expect($text)->toContain('Created note');
});

it('appends content to an existing note', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $note = \App\Models\Note::create([
        'project_id' => $project->id, 'created_by' => $project->owner_id,
        'title' => 'Log', 'content' => '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Day 1"}]}]}',
        'is_pinned' => false, 'position' => 0,
    ]);

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 11,
            'params'  => ['name' => 'update_note', 'arguments' => [
                'note_id' => $note->id,
                'append'  => 'Day 2',
            ]],
        ])
        ->assertJsonPath('result.content.0.text', fn ($t) => str_contains($t, "Updated note #{$note->id}"));

    $note->refresh();
    expect($note->content)->toContain('Day 1')->and($note->content)->toContain('Day 2');
});

it('content replaces the whole note body', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $note = \App\Models\Note::create([
        'project_id' => $project->id, 'created_by' => $project->owner_id,
        'title' => 'Replace Test', 'content' => '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Old body"}]}]}',
        'is_pinned' => false, 'position' => 0,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 12,
            'params'  => ['name' => 'update_note', 'arguments' => [
                'note_id' => $note->id,
                'content' => 'Fresh body',
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain("Updated note #{$note->id}");

    $note->refresh();
    expect($note->content)->toContain('Fresh body')
        ->and($note->content)->not->toContain('Old body');
});

it('no-op returns the No changes message', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);
    $note = \App\Models\Note::create([
        'project_id' => $project->id, 'created_by' => $project->owner_id,
        'title' => 'Unchanged', 'content' => '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Original"}]}]}',
        'is_pinned' => false, 'position' => 0,
    ]);
    $originalContent = $note->content;

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 13,
            'params'  => ['name' => 'update_note', 'arguments' => [
                'note_id' => $note->id,
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No changes');

    $note->refresh();
    expect($note->content)->toBe($originalContent);
});

it('creates a section via manage_section', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 12,
            'params'  => ['name' => 'manage_section', 'arguments' => [
                'action' => 'create', 'name' => 'In Review',
            ]],
        ])
        ->assertJsonPath('result.content.0.text', fn ($t) => str_contains($t, 'Created section'));

    expect(\App\Models\TaskSection::where('project_id', $project->id)->where('name', 'In Review')->exists())->toBeTrue();
});

it('reorder updates section positions by array order', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $sectionA = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'A', 'position' => 0]);
    $sectionB = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'B', 'position' => 1]);
    $sectionC = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'C', 'position' => 2]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 30,
            'params'  => ['name' => 'manage_section', 'arguments' => [
                'action' => 'reorder',
                'order'  => [$sectionC->id, $sectionA->id, $sectionB->id],
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Reordered 3 sections');

    expect($sectionC->fresh()->position)->toBe(0)
        ->and($sectionA->fresh()->position)->toBe(1)
        ->and($sectionB->fresh()->position)->toBe(2);
});

it('reorder rejects a foreign section id and mutates nothing', function () {
    [$raw, , $project] = mcpToken([], ['read', 'write']);

    $sectionA = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'A', 'position' => 0]);
    $sectionB = \App\Models\TaskSection::create(['project_id' => $project->id, 'name' => 'B', 'position' => 1]);

    $otherUser    = \App\Models\User::factory()->create();
    $otherProject = createProject($otherUser);
    $sectionF     = \App\Models\TaskSection::create(['project_id' => $otherProject->id, 'name' => 'F', 'position' => 0]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'method' => 'tools/call', 'id' => 31,
            'params'  => ['name' => 'manage_section', 'arguments' => [
                'action' => 'reorder',
                'order'  => [$sectionB->id, $sectionF->id],
            ]],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('not in this project');

    expect($sectionA->fresh()->position)->toBe(0)
        ->and($sectionB->fresh()->position)->toBe(1);
});
