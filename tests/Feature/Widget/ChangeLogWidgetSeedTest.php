<?php

use Illuminate\Support\Facades\DB;

it('seeds an available change_log widget', function () {
    $this->seed(\Database\Seeders\WidgetSeeder::class);

    $widget = DB::table('widgets')->where('slug', 'change_log')->first();

    expect($widget)->not->toBeNull()
        ->and((bool) $widget->is_available)->toBe(true)
        ->and($widget->category)->toBe('analytics');
});
