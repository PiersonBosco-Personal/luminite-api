<?php

use Illuminate\Support\Facades\Schema;

it('adds estimated_minutes column to tasks table', function () {
    expect(Schema::hasColumn('tasks', 'estimated_minutes'))->toBeTrue();
});
