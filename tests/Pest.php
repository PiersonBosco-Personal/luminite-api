<?php

use App\Models\Project;
use App\Models\User;
use Database\Seeders\WidgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Apply RefreshDatabase and the base TestCase to all Feature tests so every
| test file gets a clean database and the full Laravel application context.
|
*/

// Never hit a real embedding API in tests. Bound here (chained onto the Feature
// uses() so the hook actually registers — a standalone beforeEach()->in('Feature')
// in this file silently no-ops in Pest 3). Individual tests that need to assert
// embed() calls override this with app()->instance(AIProvider::class, $mock).
uses(Tests\TestCase::class, RefreshDatabase::class)
    ->beforeEach(function () {
        app()->bind(
            \App\AI\Contracts\AIProvider::class,
            \Tests\Support\FakeAiProvider::class,
        );
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Global Helper Functions
|--------------------------------------------------------------------------
|
| These helpers are available in every Feature test without any import.
| They reduce boilerplate and keep test setup consistent with real app logic.
|
*/

/**
 * Create a user and authenticate them via Sanctum (no real token created).
 */
function actingAsUser(array $overrides = []): User
{
    $user = User::factory()->create($overrides);
    Sanctum::actingAs($user);
    return $user;
}

/**
 * Create a project and attach the owner to project_members with role='owner'.
 * Mirrors what ProjectController::store does.
 */
function createProject(User $owner, array $overrides = []): Project
{
    $project = Project::factory()->create(array_merge(['owner_id' => $owner->id], $overrides));
    $project->members()->attach($owner->id, ['role' => 'owner']);
    return $project;
}

/**
 * Attach a user to a project as a member (or with a custom role).
 */
function addMemberToProject(Project $project, User $user, string $role = 'member'): void
{
    $project->members()->attach($user->id, ['role' => $role]);
}

/**
 * Create a project with an owner and one additional member.
 * Returns ['project' => Project, 'owner' => User, 'member' => User].
 */
function createProjectWithMember(): array
{
    $owner   = User::factory()->create();
    $member  = User::factory()->create();
    $project = createProject($owner);
    addMemberToProject($project, $member);

    return compact('project', 'owner', 'member');
}

/**
 * Seed the widget catalog. Required before any Widget or Dashboard test.
 */
function seedWidgets(): void
{
    (new WidgetSeeder())->run();
}

/**
 * Create a user, project, and MCP token. Returns [$rawToken, $mcpToken, $project, $user].
 */
function mcpToken(array $projectOverrides = [], array $scopes = ['read']): array
{
    $user    = User::factory()->create();
    $project = createProject($user, $projectOverrides);
    [$token, $raw] = \App\Models\McpToken::generate($user, $project, 'test-token', $scopes);
    return [$raw, $token, $project, $user];
}

/**
 * Call an MCP tool over the real HTTP endpoint and return its text result.
 * Returns the raw `result.content.0.text` string.
 */
function callTool($test, string $raw, string $name, array $arguments): string
{
    return $test->withToken($raw)
        ->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'id'      => 1,
            'params'  => ['name' => $name, 'arguments' => $arguments],
        ])
        ->assertStatus(200)
        ->json('result.content.0.text');
}
