<?php

beforeEach(function () {
    $this->seed(\Database\Seeders\WidgetSeeder::class);
});

it('GET /v1/widgets includes is_available per widget', function () {
    $user = actingAsUser();

    $data = $this->getJson('/api/v1/widgets')
        ->assertStatus(200)
        ->json('data');

    $bySlug = collect($data)->keyBy('slug');
    expect($bySlug['tasks_board']['is_available'])->toBeTrue();
    expect($bySlug['ai_chat']['is_available'])->toBeFalse();
});
