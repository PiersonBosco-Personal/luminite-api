<?php

use App\Models\Label;
use App\Models\Note;
use App\Models\User;

it('get_project_notes returns all notes when no filters given', function () {
    [$raw, , $project, $user] = mcpToken();

    Note::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'title'      => 'Auth Architecture',
        'content'    => null,
        'is_pinned'  => false,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => 'get_project_notes', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Auth Architecture');
});

it('get_project_notes filters by keyword in title', function () {
    [$raw, , $project, $user] = mcpToken();

    Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'Database Schema', 'content' => null]);
    Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'Frontend Notes', 'content' => null]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 2,
            'params'  => ['name' => 'get_project_notes', 'arguments' => ['keyword' => 'Database']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Database Schema')
        ->and($text)->not->toContain('Frontend Notes');
});

it('get_project_notes extracts plain text from tiptap json content', function () {
    [$raw, , $project, $user] = mcpToken();

    $tiptap = json_encode([
        'type'    => 'doc',
        'content' => [
            [
                'type'    => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Sanctum tokens are stored in localStorage'],
                ],
            ],
        ],
    ]);

    Note::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'title'      => 'Auth Decisions',
        'content'    => $tiptap,
        'is_pinned'  => false,
    ]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 3,
            'params'  => ['name' => 'get_project_notes', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Sanctum tokens are stored in localStorage');
});

it('get_project_notes filters by keyword in content', function () {
    [$raw, , $project, $user] = mcpToken();

    $tiptap = json_encode([
        'type'    => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Uses pgvector for embeddings']]],
        ],
    ]);

    Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'AI Notes', 'content' => $tiptap]);
    Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'Other Note', 'content' => null]);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 4,
            'params'  => ['name' => 'get_project_notes', 'arguments' => ['keyword' => 'pgvector']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('AI Notes')
        ->and($text)->not->toContain('Other Note');
});

it('get_project_notes filters by tag label name', function () {
    [$raw, , $project, $user] = mcpToken();

    $label   = Label::factory()->create(['project_id' => $project->id, 'name' => 'architecture']);
    $tagged  = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'System Design']);
    $plain   = Note::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'title' => 'Random Notes']);

    $tagged->labels()->attach($label->id);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 5,
            'params'  => ['name' => 'get_project_notes', 'arguments' => ['tag' => 'architecture']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('System Design')
        ->and($text)->not->toContain('Random Notes');
});

it('get_project_notes does not return notes from other projects', function () {
    [$raw] = mcpToken();

    $otherUser = User::factory()->create();
    $other = createProject($otherUser);
    Note::factory()->create(['project_id' => $other->id, 'created_by' => $otherUser->id, 'title' => 'Secret note']);

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 6,
            'params'  => ['name' => 'get_project_notes', 'arguments' => []],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->not->toContain('Secret note');
});

it('get_project_notes returns no-notes message when none match', function () {
    [$raw] = mcpToken();

    $text = $this->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 7,
            'params'  => ['name' => 'get_project_notes', 'arguments' => ['keyword' => 'nonexistent-xyz']],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('No notes');
});
