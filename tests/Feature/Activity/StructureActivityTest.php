<?php

use App\Models\ActivityLog;
use App\Models\Label;
use App\Models\TaskSection;
use App\Models\TechStack;

// --- Sections ---

it('logs section.created on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/sections", ['name' => 'Backlog'])
        ->assertStatus(201);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('section.created')
        ->and($log->subject_label)->toBe('Backlog');
});

it('logs section.renamed on update', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Old Name']);

    $this->putJson("/api/v1/projects/{$project->id}/sections/{$section->id}", ['name' => 'New Name'])
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('section.renamed')
        ->and($log->old_value)->toBe('Old Name')
        ->and($log->new_value)->toBe('New Name');
});

it('logs section.deleted on destroy', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $section = TaskSection::factory()->create(['project_id' => $project->id, 'name' => 'Backlog']);

    $this->deleteJson("/api/v1/projects/{$project->id}/sections/{$section->id}")
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('section.deleted')
        ->and($log->subject_label)->toBe('Backlog');
});

// --- Labels ---

it('logs label.created on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/labels", ['name' => 'Bug', 'color' => '#ff0000'])
        ->assertStatus(201);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('label.created')
        ->and($log->subject_label)->toBe('Bug');
});

it('logs label.deleted on destroy', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $label   = Label::factory()->create(['project_id' => $project->id, 'name' => 'Bug']);

    $this->deleteJson("/api/v1/projects/{$project->id}/labels/{$label->id}")
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('label.deleted')
        ->and($log->subject_label)->toBe('Bug');
});

// --- Tech Stack ---

it('logs tech_stack.added on store', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/tech-stack", ['name' => 'React', 'version' => '18'])
        ->assertStatus(201);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('tech_stack.added')
        ->and($log->subject_label)->toBe('React');
});

it('logs tech_stack.updated on update when version changes', function () {
    $user      = actingAsUser();
    $project   = createProject($user);
    $techStack = TechStack::factory()->create(['project_id' => $project->id, 'name' => 'React', 'version' => '17']);

    $this->patchJson("/api/v1/projects/{$project->id}/tech-stack/{$techStack->id}", ['version' => '18'])
        ->assertStatus(200);

    $log = ActivityLog::first();
    expect($log->event_type)->toBe('tech_stack.updated')
        ->and($log->old_value)->toContain('17')
        ->and($log->new_value)->toContain('18');
});
