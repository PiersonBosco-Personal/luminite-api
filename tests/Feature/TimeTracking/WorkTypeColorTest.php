<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkType;

it('seeds default work types with their assigned colors on project creation', function () {
    actingAsUser();

    $this->postJson('/api/v1/projects', ['name' => 'New Project'])->assertStatus(201);

    $project = Project::firstWhere('name', 'New Project');
    $byName = $project->workTypes()->get()->keyBy('name');

    expect($byName['Development']->color)->toBe('blue');
    expect($byName['Testing']->color)->toBe('green');
    expect($byName['Design']->color)->toBe('purple');
    expect($byName['Meeting']->color)->toBe('amber');
    expect($byName['Documentation']->color)->toBe('cyan');
    expect($byName['Other']->color)->toBe('slate');
});

it('exposes color on the work types listing', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'X', 'color' => 'pink']);

    $data = $this->getJson("/api/v1/projects/{$project->id}/work-types")
        ->assertStatus(200)
        ->json('data');

    expect(collect($data)->firstWhere('name', 'X')['color'])->toBe('pink');
});

it('auto-assigns the next unused palette color when creating without a color', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    // Pre-occupy the first two assignable colors.
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'A', 'color' => 'red']);
    WorkType::factory()->create(['project_id' => $project->id, 'name' => 'B', 'color' => 'orange']);

    $response = $this->postJson("/api/v1/projects/{$project->id}/work-types", ['name' => 'Code Review'])
        ->assertStatus(201);

    expect($response->json('data.color'))->toBe('amber');
});

it('falls back to slate when every assignable palette color is used', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    foreach (WorkType::ASSIGNABLE_COLORS as $i => $color) {
        WorkType::factory()->create(['project_id' => $project->id, 'name' => "T{$i}", 'color' => $color]);
    }

    $response = $this->postJson("/api/v1/projects/{$project->id}/work-types", ['name' => 'Overflow'])
        ->assertStatus(201);

    expect($response->json('data.color'))->toBe('slate');
});

it('accepts an explicit valid color slug on create', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $response = $this->postJson("/api/v1/projects/{$project->id}/work-types", [
        'name'  => 'QA',
        'color' => 'indigo',
    ])->assertStatus(201);

    expect($response->json('data.color'))->toBe('indigo');
});

it('returns 422 when an unknown color slug is sent on create', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/work-types", [
        'name'  => 'QA',
        'color' => 'puce',
    ])->assertStatus(422)
      ->assertJsonValidationErrors('color');
});

it('keeps the existing color when reactivating an inactive work type, ignoring the request color', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $archived = WorkType::factory()->create([
        'project_id' => $project->id,
        'name'       => 'Design',
        'color'      => 'purple',
        'is_active'  => false,
    ]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/work-types", [
        'name'  => 'Design',
        'color' => 'red',
    ])->assertStatus(200);

    expect($response->json('data.id'))->toBe($archived->id);
    expect($response->json('data.color'))->toBe('purple');
});

it('accepts a valid color slug on update', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $type    = WorkType::factory()->create(['project_id' => $project->id, 'color' => 'blue']);

    $this->putJson("/api/v1/projects/{$project->id}/work-types/{$type->id}", ['color' => 'teal'])
        ->assertStatus(200);

    expect($type->fresh()->color)->toBe('teal');
});

it('returns 422 when an unknown color slug is sent on update', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $type    = WorkType::factory()->create(['project_id' => $project->id, 'color' => 'blue']);

    $this->putJson("/api/v1/projects/{$project->id}/work-types/{$type->id}", ['color' => 'puce'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('color');
});

it('includes color on each group in the report when group_by is work_type', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $dev     = WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Development', 'color' => 'blue']);
    $design  = WorkType::factory()->create(['project_id' => $project->id, 'name' => 'Design',      'color' => 'purple']);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'work_type_id' => $dev->id,    'duration_minutes' => 60, 'logged_at' => today()]);
    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'work_type_id' => $design->id, 'duration_minutes' => 30, 'logged_at' => today()]);

    $groups = collect(
        $this->getJson("/api/v1/projects/{$project->id}/time-entries/report?group_by=work_type")
            ->assertStatus(200)
            ->json('groups')
    )->keyBy('label');

    expect($groups['Development']['color'])->toBe('blue');
    expect($groups['Design']['color'])->toBe('purple');
});

it('returns null color in report groups when grouped by user', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'duration_minutes' => 60]);

    $groups = $this->getJson("/api/v1/projects/{$project->id}/time-entries/report?group_by=user")
        ->assertStatus(200)
        ->json('groups');

    expect($groups[0])->toHaveKey('color');
    expect($groups[0]['color'])->toBeNull();
});

it('returns null color in report groups when grouped by task', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    TimeEntry::factory()->create(['project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $user->id, 'duration_minutes' => 60]);

    $groups = $this->getJson("/api/v1/projects/{$project->id}/time-entries/report?group_by=task")
        ->assertStatus(200)
        ->json('groups');

    expect($groups[0])->toHaveKey('color');
    expect($groups[0]['color'])->toBeNull();
});
