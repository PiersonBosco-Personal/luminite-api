<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns_generic_response_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertOk();
        Notification::assertNothingSent();
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $user->createToken('existing-session');
        $this->assertCount(1, $user->tokens()->get());

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'reset@example.com',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens()->get());
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => 'totally-invalid-token',
            'email'                 => 'reset@example.com',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(422);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'reset@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
                ->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
            ->assertStatus(429);
    }
}
