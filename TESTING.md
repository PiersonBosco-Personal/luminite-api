# Luminite API — Testing Reference

## Setup

Pest is the test runner. If not yet installed:

```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies
php artisan pest:install
```

Run the full suite:

```bash
composer test
```

This clears config and runs `php artisan test` (PHPUnit/Pest via artisan).

---

## Stack

| Tool | Purpose |
|---|---|
| Pest PHP | Test runner (built on PHPUnit) |
| Laravel Sanctum | `Sanctum::actingAs($user)` for auth in tests |
| SQLite in-memory | Fast, isolated DB per test run |
| `RefreshDatabase` | Applied globally — wipes + re-migrates before each test |

SQLite in-memory is configured in `phpunit.xml` (`DB_DATABASE=testing`) and `config/database.php` defaults to `sqlite`.

---

## Helper Functions (`tests/Pest.php`)

These are available globally in every Feature test.

```
actingAsUser(array $overrides = []): User
```
Creates a user, authenticates them via `Sanctum::actingAs()`, and returns the user. Use this for any test that needs an authenticated user.

```
createProject(User $owner, array $overrides = []): Project
```
Creates a project with `owner_id` set to `$owner->id` and attaches the owner to `project_members` with `role = 'owner'`. Mirrors what `ProjectController::store` does.

```
addMemberToProject(Project $project, User $user, string $role = 'member'): void
```
Attaches a user to a project's `project_members` pivot.

```
createProjectWithMember(): array
```
Returns `['project' => Project, 'owner' => User, 'member' => User]`. Use this as a shortcut in authorization tests that need all three entities.

```
seedWidgets(): void
```
Runs `WidgetSeeder`. Call at the start of any test in `WidgetTest` or `DashboardTest` since the widget catalog is seeded data, not factory-created.

---

## File Structure

```
tests/
  Pest.php                    ← global helpers + uses() config
  TestCase.php                ← base class (empty, uses() applied via Pest.php)
  Feature/
    Auth/AuthTest.php
    Project/ProjectCrudTest.php
    Project/ProjectMemberTest.php
    TaskSection/TaskSectionTest.php
    Task/TaskCrudTest.php
    Note/NoteTest.php
    Label/LabelTest.php
    TechStack/TechStackTest.php
    Widget/WidgetTest.php
    Dashboard/DashboardTest.php
    Ai/AiControllerTest.php

database/factories/
  UserFactory.php             ← exists
  ProjectFactory.php
  TaskSectionFactory.php
  TaskFactory.php
  NoteFactory.php
  LabelFactory.php
  TechStackFactory.php
```

---

## API URL Prefix

All routes are at `/api/v1/...`. Example:

```php
$this->getJson('/api/v1/projects')
$this->postJson('/api/v1/projects', ['name' => 'My Project'])
```

---

## Authorization Test Coverage

Every project-scoped test file covers these four cases:

| Scenario | Expected |
|---|---|
| No token | 401 |
| Authenticated but not a project member | 403 (middleware) |
| Member tries owner-only action (update/delete project, manage members) | 403 (policy) |
| Correct project member, wrong-project resource | 404 |

---

## New Feature Workflow

Follow these steps every time you build a new controller action:

1. **Write the test first (red).** Define the URL, payload, and expected response before touching the controller. This forces you to design the API contract upfront.

2. **Create a factory if the model has none.** Tests must use factories, not inline `Model::create()` calls, so data structure stays consistent.

3. **Implement until green.** Write the minimal controller/request/policy code to pass the happy-path test.

4. **Add auth and error-path tests.** At minimum:
   - Unauthenticated → 401
   - Non-member → 403 (project-scoped routes)
   - Wrong-project resource → 404
   - Missing required field → 422

5. **Run `composer test` before committing.** The full suite must pass.

---

## Naming Convention

```php
it('owner can delete the project');
it('returns 403 when user is not a project member');
it('returns 422 when name is missing');
it('places new widget below existing widgets');
```

Use plain English that reads as documentation. Avoid vague names like `it('store works')`.

---

## Test Data Rules

- Each test creates its own data from scratch. Never share state between tests.
- `RefreshDatabase` handles teardown automatically.
- Never use `$this->markTestSkipped()` as a placeholder.
- Widget/Dashboard tests must call `seedWidgets()` because the widget catalog is not factory-created.
