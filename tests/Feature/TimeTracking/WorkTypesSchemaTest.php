<?php

use Illuminate\Support\Facades\Schema;

it('creates work_types table with expected columns', function () {
    expect(Schema::hasTable('work_types'))->toBeTrue();
    expect(Schema::hasColumns('work_types', [
        'id', 'project_id', 'name', 'is_active', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('cascades work_type delete when project is force-deleted', function () {
    $owner   = \App\Models\User::factory()->create();
    $project = createProject($owner);
    \App\Models\WorkType::factory()->create(['project_id' => $project->id]);

    $project->forceDelete();

    expect(\App\Models\WorkType::count())->toBe(0);
});
