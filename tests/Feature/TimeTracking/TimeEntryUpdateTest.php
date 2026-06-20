<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Task;
use App\Models\WorkType;

it('allows owner to update their entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $entry   = TimeEntry::factory()->create([
        'project_id'       => $project->id,
        'user_id'          => $user->id,
        'duration_minutes' => 30,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'duration_minutes' => 90,
    ])->assertStatus(200);

    expect($entry->fresh()->duration_minutes)->toBe(90);
});

it('forbids non-owner from updating entry', function () {
    $owner   = User::factory()->create();
    $other   = actingAsUser();
    $project = createProject($owner);
    addMemberToProject($project, $other);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'duration_minutes' => 60,
    ])->assertStatus(403);
});

it('returns 404 when entry does not belong to the project', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $other   = createProject($user);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $other->id,
        'user_id'    => $user->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'duration_minutes' => 60,
    ])->assertStatus(404);
});

it('rejects updating with a task_id from another project', function () {
    $user         = actingAsUser();
    $project      = createProject($user);
    $otherProject = createProject($user);
    $foreignTask  = Task::factory()->create([
        'project_id' => $otherProject->id,
        'created_by' => $user->id,
    ]);
    $entry = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'task_id' => $foreignTask->id,
    ])->assertStatus(422)->assertJsonValidationErrors('task_id');
});

it('rejects updating with a work_type_id from another project', function () {
    $user         = actingAsUser();
    $project      = createProject($user);
    $otherProject = createProject($user);
    $foreignType  = WorkType::factory()->create(['project_id' => $otherProject->id]);
    $entry = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'work_type_id' => $foreignType->id,
    ])->assertStatus(422)->assertJsonValidationErrors('work_type_id');
});

it('accepts updating with a task_id and work_type_id from the same project', function () {
    $user     = actingAsUser();
    $project  = createProject($user);
    $task     = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $workType = WorkType::factory()->create(['project_id' => $project->id]);
    $entry    = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'task_id'      => $task->id,
        'work_type_id' => $workType->id,
    ])->assertStatus(200);

    expect($entry->fresh()->task_id)->toBe($task->id);
    expect($entry->fresh()->work_type_id)->toBe($workType->id);
});
