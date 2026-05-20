<?php

use App\Models\Widget;

it('seeds the time_tracker widget', function () {
    seedWidgets();

    $w = Widget::firstWhere('slug', 'time_tracker');
    expect($w)->not->toBeNull();
    expect($w->category)->toBe('productivity');
    expect($w->default_w)->toBe(4);
    expect($w->default_h)->toBe(7);
});

it('seeds the time_report widget', function () {
    seedWidgets();

    $w = Widget::firstWhere('slug', 'time_report');
    expect($w)->not->toBeNull();
    expect($w->category)->toBe('analytics');
    expect($w->default_w)->toBe(6);
    expect($w->default_h)->toBe(5);
});
