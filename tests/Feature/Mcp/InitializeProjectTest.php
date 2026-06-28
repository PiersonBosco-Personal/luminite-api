<?php

use App\Events\ProjectInitialized;
use App\Events\ProjectUpdated;
use App\Events\TaskCreated;
use App\Models\ActivityLog;
use App\Models\DashboardWidget;
use App\Models\Label;
use App\Models\McpHistory;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\TechStack;
use App\Models\Widget;
use Illuminate\Support\Facades\Event;

/** Blank project + write token. ProjectFactory fills description, so null it. */
function initBlankToken(array $scopes = ['read', 'write']): array
{
    return mcpToken(['description' => null], $scopes);
}

function initFullPayload(array $overrides = []): array
{
    return array_merge([
        'details' => [
            'description' => 'A task manager for small dev teams.',
            'goals' => 'Ship v1.',
            'architecture_notes' => 'Laravel API + React PWA.',
        ],
        'tech_stack' => [
            ['name' => 'Laravel', 'version' => '11', 'children' => [['name' => 'Reverb']]],
            ['name' => 'React', 'version' => '18'],
        ],
        'sections' => ['Backlog', 'In Progress', 'Done'],
        'labels' => [
            ['name' => 'bug', 'color' => '#c0392b'],
            ['name' => 'feature', 'color' => '#2ebbcc'],
        ],
        'tasks' => [
            ['title' => 'Set up CI', 'section' => 0, 'priority' => 'high', 'labels' => [1]],
            ['title' => 'Fix login', 'section' => 0, 'description' => 'Token bug.', 'labels' => [0, 1]],
            ['title' => 'Write docs', 'section' => 2],
        ],
        'widgets' => ['tasks_board', 'activity_feed'],
    ], $overrides);
}

function callInitializeProject(string $raw, array $arguments)
{
    return test()->withToken($raw)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'id' => 1,
        'params' => ['name' => 'initialize_project', 'arguments' => $arguments],
    ]);
}

it('initializes a blank project end to end', function () {
    seedWidgets();
    [$raw, , $project, $user] = initBlankToken();

    $text = callInitializeProject($raw, initFullPayload())
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Initialized project')
        ->and($text)->toContain('3 tech stack entries')
        ->and($text)->toContain('3 sections')
        ->and($text)->toContain('2 labels')
        ->and($text)->toContain('3 tasks')
        ->and($text)->toContain('2 widgets placed');

    $project->refresh();
    expect($project->description)->toBe('A task manager for small dev teams.')
        ->and($project->goals)->toBe('Ship v1.')
        ->and($project->architecture_notes)->toBe('Laravel API + React PWA.');

    // tech stack: parent/child link
    $laravel = TechStack::where('project_id', $project->id)->where('name', 'Laravel')->first();
    $reverb = TechStack::where('project_id', $project->id)->where('name', 'Reverb')->first();
    expect(TechStack::where('project_id', $project->id)->count())->toBe(3)
        ->and($reverb->parent_id)->toBe($laravel->id);

    // sections in payload order
    $names = TaskSection::where('project_id', $project->id)->orderBy('position')->pluck('name')->all();
    expect($names)->toBe(['Backlog', 'In Progress', 'Done']);

    // labels with their hex colors
    expect(Label::where('project_id', $project->id)->where('name', 'bug')->first()->color)->toBe('#c0392b');

    // tasks: index-resolved section + labels, per-section positions
    $fix = Task::where('project_id', $project->id)->where('title', 'Fix login')->first();
    $backlog = TaskSection::where('project_id', $project->id)->where('name', 'Backlog')->first();
    expect(Task::where('project_id', $project->id)->count())->toBe(3)
        ->and($fix->section_id)->toBe($backlog->id)
        ->and((int) $fix->position)->toBe(1)
        ->and($fix->priority)->toBe('medium')
        ->and($fix->created_by)->toBe($user->id)
        ->and($fix->labels->pluck('name')->sort()->values()->all())->toBe(['bug', 'feature']);

    $docs = Task::where('project_id', $project->id)->where('title', 'Write docs')->first();
    expect((int) $docs->position)->toBe(0); // first task in its own section

    // widgets on the token user's dashboard
    expect(DashboardWidget::where('project_id', $project->id)->where('user_id', $user->id)->count())->toBe(2);

    // exactly one activity row, via MCP
    $log = ActivityLog::where('project_id', $project->id)->where('event_type', 'project.initialized')->get();
    expect($log)->toHaveCount(1)
        ->and($log->first()->via_mcp)->toBeTrue();

    // mcp_history row (automatic via McpServer)
    expect(McpHistory::where('tool', 'initialize_project')->where('status', 'success')->count())->toBe(1);
});

it('broadcasts a single ProjectInitialized event and no per-task events', function () {
    seedWidgets();
    [$raw, , $project] = initBlankToken();

    Event::fake([ProjectInitialized::class, TaskCreated::class, ProjectUpdated::class]);

    callInitializeProject($raw, initFullPayload())->assertStatus(200);

    Event::assertDispatchedTimes(ProjectInitialized::class, 1);
    Event::assertDispatched(ProjectInitialized::class, fn ($e) => $e->projectId === $project->id);
    Event::assertNotDispatched(TaskCreated::class);  // replaced by the single init event
    Event::assertNotDispatched(ProjectUpdated::class);
});

it('does not broadcast ProjectInitialized when the project is not blank', function () {
    seedWidgets();
    [$raw, , $project] = initBlankToken();
    $project->update(['description' => 'existing']);

    Event::fake([ProjectInitialized::class]);

    callInitializeProject($raw, initFullPayload())->assertStatus(200);

    Event::assertNotDispatched(ProjectInitialized::class);
});

it('refuses when the project is not blank', function (callable $setup) {
    seedWidgets();
    [$raw, , $project] = initBlankToken();
    $setup($project);

    $text = callInitializeProject($raw, initFullPayload())
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('already initialized')
        ->and(Task::where('project_id', $project->id)->where('title', 'Set up CI')->exists())->toBeFalse()
        ->and(TechStack::where('project_id', $project->id)->where('name', 'Laravel')->exists())->toBeFalse();
})->with([
    'description' => fn ($p) => $p->update(['description' => 'existing']),
    'goals' => fn ($p) => $p->update(['goals' => 'existing']),
    'architecture_notes' => fn ($p) => $p->update(['architecture_notes' => 'existing']),
    'tech stack' => fn ($p) => TechStack::factory()->create(['project_id' => $p->id]),
    'sections' => fn ($p) => TaskSection::factory()->create(['project_id' => $p->id]),
    'labels' => fn ($p) => Label::factory()->create(['project_id' => $p->id]),
    'tasks' => fn ($p) => Task::factory()->create([
        'project_id' => $p->id,
        'section_id' => TaskSection::factory()->create(['project_id' => $p->id])->id,
    ]),
]);

it('does not treat existing widgets as non-blank, and skips already-placed slugs', function () {
    seedWidgets();
    [$raw, , $project, $user] = initBlankToken();

    $board = Widget::where('slug', 'tasks_board')->first();
    DashboardWidget::create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'widget_id' => $board->id,
        'grid_x' => 0,
        'grid_y' => 0,
        'grid_w' => $board->default_w,
        'grid_h' => $board->default_h,
    ]);

    $text = callInitializeProject($raw, initFullPayload())
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toContain('Initialized project')
        ->and($text)->toContain('1 widgets placed'); // tasks_board skipped, activity_feed placed

    expect(DashboardWidget::where('project_id', $project->id)
        ->where('user_id', $user->id)
        ->where('widget_id', $board->id)
        ->count())->toBe(1); // not duplicated

    // The new widget slots into the gap beside the pre-existing board, not below it.
    $feed = Widget::where('slug', 'activity_feed')->first();
    $placed = DashboardWidget::where('project_id', $project->id)
        ->where('user_id', $user->id)
        ->where('widget_id', $feed->id)
        ->first();
    expect((int) $placed->grid_x)->toBe(8)
        ->and((int) $placed->grid_y)->toBe(0);
});

it('stores omitted goals and architecture notes as null', function () {
    seedWidgets();
    [$raw, , $project] = initBlankToken();

    callInitializeProject($raw, initFullPayload([
        'details' => ['description' => 'Only a description.'],
    ]))->assertStatus(200);

    $project->refresh();
    expect($project->goals)->toBeNull()
        ->and($project->architecture_notes)->toBeNull();
});

it('denies a read-only token', function () {
    seedWidgets();
    [$raw, , $project] = initBlankToken(['read']);

    callInitializeProject($raw, initFullPayload())
        ->assertStatus(200)
        ->assertJsonPath('error.code', -32603);

    expect(Task::where('project_id', $project->id)->exists())->toBeFalse();
});

it('rejects invalid payloads end to end and writes nothing', function (array $overrides, string $needle) {
    seedWidgets();
    [$raw, , $project] = initBlankToken();

    $text = callInitializeProject($raw, initFullPayload($overrides))
        ->assertStatus(200)
        ->json('result.content.0.text');

    expect($text)->toStartWith('Error:')
        ->and($text)->toContain($needle);

    $project->refresh();
    expect($project->description)->toBeNull()
        ->and(TaskSection::where('project_id', $project->id)->exists())->toBeFalse()
        ->and(Task::where('project_id', $project->id)->exists())->toBeFalse();
})->with([
    'over cap' => [['sections' => ['a', 'b', 'c', 'd', 'e', 'f', 'g'], 'tasks' => []], 'sections exceeds 6'],
    'bad color format' => [['labels' => [['name' => 'x', 'color' => 'red']], 'tasks' => []], 'labels[0].color'],
    'index range' => [['tasks' => [['title' => 't', 'section' => 99]]], 'tasks[0].section'],
    'unknown key' => [['hax' => true], "Unknown key 'hax'"],
    'unknown widget' => [['widgets' => ['not_a_widget']], 'widgets[0]'],
]);

