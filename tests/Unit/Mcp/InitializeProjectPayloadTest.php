<?php

use App\Mcp\Tools\ToolInputException;
use App\Mcp\Validation\InitializeProjectPayload;

const VALID_SLUGS = ['tasks_board', 'notes_list', 'activity_feed'];

function validPayload(array $overrides = []): array
{
    return array_merge([
        'details' => [
            'description'        => 'A task manager for small dev teams.',
            'goals'              => 'Ship v1.',
            'architecture_notes' => 'Laravel API + React PWA.',
        ],
        'tech_stack' => [
            ['name' => 'Laravel', 'version' => '11', 'children' => [['name' => 'Reverb']]],
            ['name' => 'React', 'version' => '18'],
        ],
        'sections' => ['Backlog', 'In Progress', 'Done'],
        'labels'   => [
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

function check(array $payload): array
{
    return (new InitializeProjectPayload())->validate($payload, VALID_SLUGS);
}

it('accepts and normalizes a valid full payload', function () {
    $out = check(validPayload());

    expect($out['details']['description'])->toBe('A task manager for small dev teams.')
        ->and($out['sections'])->toBe(['Backlog', 'In Progress', 'Done'])
        ->and($out['tech_stack'][0]['children'][0]['name'])->toBe('Reverb')
        ->and($out['tasks'][2]['priority'])->toBe('medium')   // default applied
        ->and($out['tasks'][2]['labels'])->toBe([])
        ->and($out['widgets'])->toBe(['tasks_board', 'activity_feed']);
});

it('accepts a minimal payload (details only)', function () {
    $out = check(['details' => ['description' => 'Just a description.']]);

    expect($out['details']['description'])->toBe('Just a description.')
        ->and($out['sections'])->toBe([])
        ->and($out['tasks'])->toBe([]);
});

it('strips control characters but keeps newlines and tabs', function () {
    $out = check(['details' => ['description' => "Line one\nLine\ttwo\x00\x07\x1B"]]);

    expect($out['details']['description'])->toBe("Line one\nLine\ttwo");
});

it('requires details.description', function () {
    check(['details' => ['goals' => 'g']]);
})->throws(ToolInputException::class, 'details.description is required');

it('rejects each over-cap field', function (array $payload, string $needle) {
    try {
        check($payload);
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain($needle);
    }
})->with([
    'description 5001'      => fn () => [validPayload(['details' => ['description' => str_repeat('a', 5001)]]), 'details.description exceeds 5000'],
    'goals 5001'            => fn () => [validPayload(['details' => ['description' => 'd', 'goals' => str_repeat('a', 5001)]]), 'details.goals exceeds 5000'],
    'arch notes 5001'       => fn () => [validPayload(['details' => ['description' => 'd', 'architecture_notes' => str_repeat('a', 5001)]]), 'details.architecture_notes exceeds 5000'],
    'tech_stack 31 total'   => fn () => [validPayload(['tech_stack' => array_map(fn ($i) => ['name' => "t{$i}"], range(1, 31))]), 'tech_stack exceeds 30'],
    'sections 7'            => fn () => [validPayload(['sections' => ['a', 'b', 'c', 'd', 'e', 'f', 'g'], 'tasks' => []]), 'sections exceeds 6'],
    'labels 11'             => fn () => [validPayload(['labels' => array_map(fn ($i) => ['name' => "l{$i}", 'color' => '#94a3b8'], range(1, 11)), 'tasks' => []]), 'labels exceeds 10'],
    'tasks 26'              => fn () => [validPayload(['tasks' => array_map(fn ($i) => ['title' => "t{$i}", 'section' => 0], range(1, 26))]), 'tasks exceeds 25'],
    'widgets 7'             => fn () => [validPayload(['widgets' => array_fill(0, 7, 'tasks_board')]), 'widgets exceeds 6'],
    'title 201'             => fn () => [validPayload(['tasks' => [['title' => str_repeat('a', 201), 'section' => 0]]]), 'tasks[0].title'],
    'task description 2001' => fn () => [validPayload(['tasks' => [['title' => 't', 'section' => 0, 'description' => str_repeat('a', 2001)]]]), 'tasks[0].description exceeds 2000'],
]);

it('counts tech_stack children toward the 30 cap', function () {
    $children = array_map(fn ($i) => ['name' => "c{$i}"], range(1, 30));

    try {
        check(validPayload(['tech_stack' => [['name' => 'parent', 'children' => $children]]]));
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain('tech_stack exceeds 30');
    }
});

it('accepts any #RRGGBB hex label color and lowercases it', function () {
    $out = check(validPayload(['labels' => [['name' => 'x', 'color' => '#AB12EF']], 'tasks' => []]));

    expect($out['labels'][0]['color'])->toBe('#ab12ef');
});

it('rejects bad enums and formats', function (array $payload, string $needle) {
    try {
        check($payload);
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain($needle);
    }
})->with([
    'bad priority'      => fn () => [validPayload(['tasks' => [['title' => 't', 'section' => 0, 'priority' => 'asap']]]), 'tasks[0].priority'],
    'named label color' => fn () => [validPayload(['labels' => [['name' => 'x', 'color' => 'red']], 'tasks' => []]), 'labels[0].color'],
    'short hex color'   => fn () => [validPayload(['labels' => [['name' => 'x', 'color' => '#fff']], 'tasks' => []]), 'labels[0].color'],
    'unknown widget'    => fn () => [validPayload(['widgets' => ['nonexistent_widget']]), 'widgets[0]'],
]);

it('rejects out-of-range indexes', function (array $payload, string $needle) {
    try {
        check($payload);
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain($needle);
    }
})->with([
    'section index too high' => fn () => [validPayload(['tasks' => [['title' => 't', 'section' => 3]]]), 'tasks[0].section'],
    'section index negative' => fn () => [validPayload(['tasks' => [['title' => 't', 'section' => -1]]]), 'tasks[0].section'],
    'section as string'      => fn () => [validPayload(['tasks' => [['title' => 't', 'section' => 'Backlog']]]), 'tasks[0].section'],
    'label index too high'   => fn () => [validPayload(['tasks' => [['title' => 't', 'section' => 0, 'labels' => [2]]]]), 'tasks[0].labels'],
    'task with no sections'  => fn () => [['details' => ['description' => 'd'], 'tasks' => [['title' => 't', 'section' => 0]]], 'tasks[0].section'],
]);

it('rejects unknown keys at every level', function (array $payload, string $needle) {
    try {
        check($payload);
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain($needle);
    }
})->with([
    'top level'  => fn () => [validPayload(['notes' => ['sneaky']]), "Unknown key 'notes' in payload"],
    'details'    => fn () => [validPayload(['details' => ['description' => 'd', 'id' => 9]]), "Unknown key 'id' in details"],
    'task'       => fn () => [validPayload(['tasks' => [['title' => 't', 'section' => 0, 'assigned_to' => 1]]]), "Unknown key 'assigned_to' in tasks[0]"],
    'label'      => fn () => [validPayload(['labels' => [['name' => 'x', 'color' => '#94a3b8', 'project_id' => 9]], 'tasks' => []]), "Unknown key 'project_id' in labels[0]"],
    'stack child' => fn () => [validPayload(['tech_stack' => [['name' => 'p', 'children' => [['name' => 'c', 'children' => []]]]]]), "Unknown key 'children' in tech_stack[0].children[0]"],
]);

it('reports every problem in one exception', function () {
    try {
        check(validPayload([
            'details'  => ['description' => ''],
            'sections' => ['a', 'b', 'c', 'd', 'e', 'f', 'g'],
            'tasks'    => [],
        ]));
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain('details.description is required')
            ->and($e->getMessage())->toContain('sections exceeds 6');
    }
});

it('de-duplicates widget slugs', function () {
    $out = check(validPayload(['widgets' => ['tasks_board', 'tasks_board']]));

    expect($out['widgets'])->toBe(['tasks_board']);
});

it('caps error accumulation on hostile over-cap payloads', function () {
    $tasks = array_map(fn ($i) => ['title' => '', 'bogus' => 1], range(1, 1000));

    try {
        check(validPayload(['tasks' => $tasks]));
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain('tasks exceeds 25')
            ->and(strlen($e->getMessage()))->toBeLessThan(10000);
    }
});

it('reports wrong-typed values instead of silently dropping them', function (array $payload, string $needle) {
    try {
        check($payload);
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect($e->getMessage())->toContain($needle);
    }
})->with([
    'numeric version'    => fn () => [validPayload(['tech_stack' => [['name' => 'Laravel', 'version' => 11]]]), 'tech_stack[0].version must be a string'],
    'array description'  => fn () => [validPayload(['details' => ['description' => ['nope']]]), 'details.description must be a string'],
    'invalid utf8'       => fn () => [validPayload(['details' => ['description' => "valid start \xC3\x28 invalid"]]), 'details.description contains invalid UTF-8'],
]);

it('still treats absent optional fields as empty without errors', function () {
    $out = check(['details' => ['description' => 'Just a description.']]);

    expect($out['details']['goals'])->toBe('');
});

it('sanitizes hostile unknown key names in error messages', function () {
    try {
        check(validPayload([str_repeat('k', 100000) => 1, "ev\xC3\x28il" => 2]));
        $this->fail('Expected ToolInputException');
    } catch (ToolInputException $e) {
        expect(strlen($e->getMessage()))->toBeLessThan(10000)
            ->and(json_encode($e->getMessage()))->not->toBeFalse();
    }
});
