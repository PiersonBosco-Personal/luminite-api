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
