<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a reset notification to a known email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('returns a generic 200 for an unknown email without sending anything', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk();

    Notification::assertNothingSent();
});

it('resets the password and revokes all sanctum tokens', function () {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $user->createToken('existing-session');
    expect($user->tokens()->count())->toBe(1);

    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token'                 => $token,
        'email'                 => 'reset@example.com',
        'password'              => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('rejects a reset with an invalid token', function () {
    User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token'                 => 'totally-invalid-token',
        'email'                 => 'reset@example.com',
        'password'              => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(422);
});

it('rate limits forgot-password after 5 attempts', function () {
    Notification::fake();
    User::factory()->create(['email' => 'reset@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
            ->assertOk();
    }

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertStatus(429);
});

it('does not allow a reset token to be reused', function () {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token'                 => $token,
        'email'                 => 'reset@example.com',
        'password'              => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    // Same token a second time must fail (single-use).
    $this->postJson('/api/v1/auth/reset-password', [
        'token'                 => $token,
        'email'                 => 'reset@example.com',
        'password'              => 'another-password-456',
        'password_confirmation' => 'another-password-456',
    ])->assertStatus(422);
});

it('returns 422 when the password confirmation does not match', function () {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token'                 => $token,
        'email'                 => 'reset@example.com',
        'password'              => 'new-password-123',
        'password_confirmation' => 'different-456',
    ])->assertStatus(422);
});
