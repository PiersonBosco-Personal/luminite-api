<?php

use App\Events\TimeEntryLogged;
use App\Events\TimerStarted;
use App\Events\TimerStopped;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([TimerStarted::class, TimerStopped::class, TimeEntryLogged::class]);
});

it('broadcasts TimerStarted on timer start', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->postJson("/api/v1/projects/{$project->id}/time-entries/timer/start", [
        'task_id' => $task->id,
    ])->assertStatus(201);

    Event::assertDispatched(TimerStarted::class);
});

it('broadcasts TimerStopped and TimeEntryLogged on timer stop', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    TimeEntry::factory()->running()->create([
        'project_id' => $project->id,
        'task_id'    => Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id])->id,
        'user_id'    => $user->id,
    ]);

    $this->postJson("/api/v1/projects/{$project->id}/time-entries/timer/stop")->assertStatus(200);

    Event::assertDispatched(TimerStopped::class);
    Event::assertDispatched(TimeEntryLogged::class);
});

it('broadcasts TimeEntryLogged on manual store', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $task    = Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->postJson("/api/v1/projects/{$project->id}/time-entries", [
        'task_id'          => $task->id,
        'duration_minutes' => 60,
    ])->assertStatus(201);

    Event::assertDispatched(TimeEntryLogged::class);
});

it('broadcasts TimeEntryLogged on update', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $entry   = TimeEntry::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
        'task_id'    => Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id])->id,
    ]);

    $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entry->id}", [
        'duration_minutes' => 90,
    ])->assertStatus(200);

    Event::assertDispatched(TimeEntryLogged::class);
});
