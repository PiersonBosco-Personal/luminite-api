<?php

use App\Models\ActivityLog;
use App\Services\ActivityLogService;

it('creates a new activity log entry', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $service = app(ActivityLogService::class);
    $log = $service->log(
        projectId:    $project->id,
        userId:       $user->id,
        eventType:    'task.created',
        subjectType:  'task',
        subjectLabel: 'Build login page',
        subjectId:    42,
        description:  "{$user->name} created task Build login page",
    );

    expect(ActivityLog::count())->toBe(1)
        ->and($log->event_type)->toBe('task.created')
        ->and($log->subject_label)->toBe('Build login page')
        ->and($log->description)->toContain('created task');
});

it('debounces same field change within 5 minutes', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $service = app(ActivityLogService::class);

    $service->log(
        projectId:    $project->id,
        userId:       $user->id,
        eventType:    'task.due_date_changed',
        subjectType:  'task',
        subjectLabel: 'Build login page',
        subjectId:    42,
        description:  "{$user->name} changed due date: Dec 1 → Dec 5",
        oldValue:     'Dec 1',
        newValue:     'Dec 5',
        fieldChanged: 'due_date',
    );

    $service->log(
        projectId:    $project->id,
        userId:       $user->id,
        eventType:    'task.due_date_changed',
        subjectType:  'task',
        subjectLabel: 'Build login page',
        subjectId:    42,
        description:  "{$user->name} changed due date: Dec 1 → Dec 15",
        oldValue:     'Dec 1',
        newValue:     'Dec 15',
        fieldChanged: 'due_date',
    );

    expect(ActivityLog::count())->toBe(1);

    $log = ActivityLog::first();
    expect($log->old_value)->toBe('Dec 1')    // original preserved
        ->and($log->new_value)->toBe('Dec 15'); // latest applied
});

it('does not debounce entries older than 5 minutes', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $service = app(ActivityLogService::class);

    $old = $service->log(
        projectId:    $project->id,
        userId:       $user->id,
        eventType:    'task.due_date_changed',
        subjectType:  'task',
        subjectLabel: 'Build login page',
        subjectId:    42,
        description:  "{$user->name} changed due date: Dec 1 → Dec 5",
        oldValue:     'Dec 1',
        newValue:     'Dec 5',
        fieldChanged: 'due_date',
    );

    // Manually age the first entry
    $old->update(['created_at' => now()->subMinutes(6)]);

    $service->log(
        projectId:    $project->id,
        userId:       $user->id,
        eventType:    'task.due_date_changed',
        subjectType:  'task',
        subjectLabel: 'Build login page',
        subjectId:    42,
        description:  "{$user->name} changed due date: Dec 5 → Dec 15",
        oldValue:     'Dec 5',
        newValue:     'Dec 15',
        fieldChanged: 'due_date',
    );

    expect(ActivityLog::count())->toBe(2);
});

it('does not debounce when fieldChanged is null', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    $service = app(ActivityLogService::class);

    $service->log(
        projectId:    $project->id,
        userId:       $user->id,
        eventType:    'task.completed',
        subjectType:  'task',
        subjectLabel: 'Build login page',
        subjectId:    42,
        description:  "{$user->name} completed Build login page",
    );

    $service->log(
        projectId:    $project->id,
        userId:       $user->id,
        eventType:    'task.completed',
        subjectType:  'task',
        subjectLabel: 'Build login page',
        subjectId:    42,
        description:  "{$user->name} completed Build login page",
    );

    expect(ActivityLog::count())->toBe(2);
});
