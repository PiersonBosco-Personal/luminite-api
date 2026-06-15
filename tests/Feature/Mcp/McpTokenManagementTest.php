<?php

use App\Events\McpTokenUpdated;
use App\Models\McpToken;
use App\Models\User;
use Illuminate\Support\Facades\Event;

// --- Generate ---

it('can generate a new MCP token', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $response = $this->postJson('/api/v1/mcp-tokens', [
        'name'       => 'My Token',
        'project_id' => $project->id,
        'scopes'     => ['read'],
    ])->assertStatus(201);

    expect($response->json('data.raw_token'))->not->toBeNull()
        ->and($response->json('data.name'))->toBe('My Token');

    $this->assertDatabaseHas('mcp_tokens', [
        'name'    => 'My Token',
        'user_id' => $user->id,
    ]);
});

it('stores only the hashed token, never the raw value', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $response = $this->postJson('/api/v1/mcp-tokens', [
        'name'       => 'My Token',
        'project_id' => $project->id,
    ])->assertStatus(201);

    $raw = $response->json('data.raw_token');
    $this->assertDatabaseMissing('mcp_tokens', ['token' => $raw]);
    $this->assertDatabaseHas('mcp_tokens', ['token' => hash('sha256', $raw)]);
});

it('defaults scopes to read when not specified', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $response = $this->postJson('/api/v1/mcp-tokens', [
        'name'       => 'My Token',
        'project_id' => $project->id,
    ])->assertStatus(201);

    expect($response->json('data.scopes'))->toBe(['read']);
});

it('returns 403 when generating a token for a project the user is not a member of', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->postJson('/api/v1/mcp-tokens', [
        'name'       => 'Token',
        'project_id' => $project->id,
    ])->assertStatus(403);
});

it('returns 422 when name is missing', function () {
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson('/api/v1/mcp-tokens', [
        'project_id' => $project->id,
    ])->assertStatus(422);
});

it('returns 422 when project_id is missing', function () {
    actingAsUser();

    $this->postJson('/api/v1/mcp-tokens', [
        'name' => 'Token',
    ])->assertStatus(422);
});

// --- List ---

it('can list own MCP tokens without exposing raw values', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    McpToken::generate($user, $project, 'Token A', ['read']);
    McpToken::generate($user, $project, 'Token B', ['read', 'write']);

    $data = $this->getJson('/api/v1/mcp-tokens')
         ->assertStatus(200)
         ->json('data');

    expect(count($data))->toBe(2)
        ->and(collect($data)->pluck('name')->all())->toContain('Token A')
        ->and($data[0])->not->toHaveKey('raw_token')
        ->and($data[0])->not->toHaveKey('token');
});

it('cannot see another user\'s tokens in the list', function () {
    $user    = actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);
    McpToken::generate($other, $project, 'Other Token', ['read']);

    $data = $this->getJson('/api/v1/mcp-tokens')->json('data');
    expect(count($data))->toBe(0);
});

// --- Revoke ---

it('can revoke own token', function () {
    $user    = actingAsUser();
    $project = createProject($user);
    [$token] = McpToken::generate($user, $project, 'Token', ['read']);

    $this->deleteJson("/api/v1/mcp-tokens/{$token->id}")->assertStatus(200);

    expect(McpToken::find($token->id))->toBeNull();
});

it('cannot revoke another user\'s token', function () {
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);
    [$token] = McpToken::generate($other, $project, 'Other Token', ['read']);

    $this->deleteJson("/api/v1/mcp-tokens/{$token->id}")->assertStatus(404);
});

// --- Real-time broadcast ---

it('broadcasts mcp.token.updated on the project channel when a token is generated', function () {
    Event::fake([McpTokenUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson('/api/v1/mcp-tokens', [
        'name'       => 'My Token',
        'project_id' => $project->id,
    ])->assertStatus(201);

    Event::assertDispatched(
        McpTokenUpdated::class,
        fn (McpTokenUpdated $e) => $e->projectId === $project->id
            && $e->action === 'created'
            && $e->broadcastAs() === 'mcp.token.updated',
    );
});

it('broadcasts mcp.token.updated on the project channel when a token is revoked', function () {
    Event::fake([McpTokenUpdated::class]);

    $user    = actingAsUser();
    $project = createProject($user);
    [$token] = McpToken::generate($user, $project, 'Token', ['read']);

    $this->deleteJson("/api/v1/mcp-tokens/{$token->id}")->assertStatus(200);

    Event::assertDispatched(
        McpTokenUpdated::class,
        fn (McpTokenUpdated $e) => $e->projectId === $project->id
            && $e->tokenId === $token->id
            && $e->action === 'revoked',
    );
});

it('returns 401 when not authenticated on list', function () {
    $this->getJson('/api/v1/mcp-tokens')->assertStatus(401);
});

it('returns 401 when not authenticated on generate', function () {
    $this->postJson('/api/v1/mcp-tokens', [])->assertStatus(401);
});
