<?php

it('returns only active widgets for authenticated user', function () {
    seedWidgets();
    actingAsUser();

    $data = $this->getJson('/api/v1/widgets')
                 ->assertStatus(200)
                 ->json('data');

    expect(count($data))->toBeGreaterThan(0);
    foreach ($data as $widget) {
        expect($widget['is_active'])->toBeTrue();
    }
});

it('returns 401 on widget catalog when unauthenticated', function () {
    $this->getJson('/api/v1/widgets')->assertStatus(401);
});
