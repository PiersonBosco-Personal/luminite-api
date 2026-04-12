<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

// --- Register ---

it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name'                  => 'John Doe',
        'email'                 => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
});

it('returns 422 when name is missing on register', function () {
    $this->postJson('/api/v1/auth/register', [
        'email'                 => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422);
});

it('returns 422 when email is invalid on register', function () {
    $this->postJson('/api/v1/auth/register', [
        'name'                  => 'John Doe',
        'email'                 => 'not-an-email',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422);
});

it('returns 422 when email is already taken on register', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name'                  => 'John Doe',
        'email'                 => 'taken@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422);
});

it('returns 422 when password is too short on register', function () {
    $this->postJson('/api/v1/auth/register', [
        'name'                  => 'John Doe',
        'email'                 => 'john@example.com',
        'password'              => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422);
});

it('returns 422 when password confirmation is missing on register', function () {
    $this->postJson('/api/v1/auth/register', [
        'name'     => 'John Doe',
        'email'    => 'john@example.com',
        'password' => 'password123',
    ])->assertStatus(422);
});

// --- Login ---

it('logs in with valid credentials and returns a token', function () {
    User::factory()->create(['email' => 'john@example.com', 'password' => bcrypt('password123')]);

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'john@example.com',
        'password' => 'password123',
    ])->assertStatus(200)->assertJsonStructure(['token', 'user']);
});

it('returns 401 when password is wrong on login', function () {
    User::factory()->create(['email' => 'john@example.com', 'password' => bcrypt('correct')]);

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'john@example.com',
        'password' => 'wrong',
    ])->assertStatus(401);
});

it('returns 422 when email is missing on login', function () {
    $this->postJson('/api/v1/auth/login', [
        'password' => 'password123',
    ])->assertStatus(422);
});

// --- Logout ---

it('logs out and deletes the token', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertStatus(200);

    // Clear the in-memory auth guard cache so the next request re-authenticates from the DB
    auth()->forgetGuards();

    // Token should no longer work
    $this->withToken($token)
        ->getJson('/api/v1/auth/user')
        ->assertStatus(401);
});

it('returns 401 when logging out without a token', function () {
    $this->postJson('/api/v1/auth/logout')->assertStatus(401);
});

// --- Current User ---

it('returns the authenticated user', function () {
    $user = actingAsUser();

    $this->getJson('/api/v1/auth/user')
        ->assertStatus(200)
        ->assertJsonFragment(['email' => $user->email]);
});

it('returns 401 when fetching user without a token', function () {
    $this->getJson('/api/v1/auth/user')->assertStatus(401);
});
