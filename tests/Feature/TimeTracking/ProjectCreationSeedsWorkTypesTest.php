<?php

use App\Models\Project;

it('seeds six default work types on project creation', function () {
    actingAsUser();

    $this->postJson('/api/v1/projects', [
        'name' => 'New Project',
    ])->assertStatus(201);

    $project = Project::firstWhere('name', 'New Project');

    $names = $project->workTypes()->pluck('name')->all();

    expect($names)->toEqualCanonicalizing([
        'Development', 'Testing', 'Design', 'Meeting', 'Documentation', 'Other',
    ]);
});

it('seeds work types independently for each new project', function () {
    actingAsUser();

    $this->postJson('/api/v1/projects', ['name' => 'P1'])->assertStatus(201);
    $this->postJson('/api/v1/projects', ['name' => 'P2'])->assertStatus(201);

    expect(\App\Models\WorkType::count())->toBe(12); // 6 per project
});
