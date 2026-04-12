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

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

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
