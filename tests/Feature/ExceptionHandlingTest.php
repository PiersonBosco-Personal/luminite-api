<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

it('returns the app envelope with a correlation id for an unhandled API 500', function () {
    Route::get('/api/v1/_throw', function () {
        throw new \RuntimeException('super secret internal detail');
    });

    $res = $this->getJson('/api/v1/_throw')->assertStatus(500);

    $res->assertJsonStructure(['data', 'message', 'errors', 'error_id']);
    expect($res->json('error_id'))->toBeString()->not->toBeEmpty();
    // The real exception message must never reach the client.
    expect($res->json('message'))->not->toContain('secret');
});

it('logs the unhandled exception with the same correlation id and request context', function () {
    Log::spy();

    Route::get('/api/v1/_throw', function () {
        throw new \RuntimeException('boom');
    });

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $errorId = $this->getJson('/api/v1/_throw')->assertStatus(500)->json('error_id');

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($errorId, $user) {
        return ($context['error_id'] ?? null) === $errorId
            && ($context['user_id'] ?? null) === $user->id
            && ($context['route'] ?? $context['url'] ?? null) !== null;
    });
});

it('leaves framework-handled responses untouched (validation stays 422)', function () {
    // login() validates email/password; a missing body is a 422, not a 500 envelope.
    $res = $this->postJson('/api/v1/auth/login', [])->assertStatus(422);

    expect($res->json('error_id'))->toBeNull();
});

it('returns 404 (not a 500 envelope) for a missing model-bound record', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/projects/999999')->assertStatus(404);
});
