<?php

use Illuminate\Support\Facades\Schema;

it('creates time_entries table with expected columns', function () {
    expect(Schema::hasTable('time_entries'))->toBeTrue();
    expect(Schema::hasColumns('time_entries', [
        'id', 'project_id', 'task_id', 'user_id', 'work_type_id',
        'description', 'duration_minutes',
        'started_at', 'stopped_at', 'logged_at',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});
