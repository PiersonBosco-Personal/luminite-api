<?php

use App\Models\DashboardWidget;
use App\Models\User;
use App\Models\Widget;
use Laravel\Sanctum\Sanctum;

// --- Index ---

it('returns only the acting user\'s dashboard widgets for the project', function () {
    seedWidgets();

    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    Sanctum::actingAs($owner);

    $widget = Widget::first();

    // Owner has 1 widget, member has 1 widget
    DashboardWidget::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
        'widget_id'  => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);
    DashboardWidget::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $member->id,
        'widget_id'  => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);

    $data = $this->getJson("/api/v1/projects/{$project->id}/dashboard-widgets")
                 ->assertStatus(200)
                 ->json('data');

    expect(count($data))->toBe(1)
        ->and($data[0]['user_id'])->toBe($owner->id);
});

it('returns 403 on dashboard index when not a member', function () {
    seedWidgets();
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);

    $this->getJson("/api/v1/projects/{$project->id}/dashboard-widgets")->assertStatus(403);
});

// --- Store ---

it('member can add a widget to the dashboard', function () {
    seedWidgets();
    $user    = actingAsUser();
    $project = createProject($user);
    $widget  = Widget::first();

    $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets", [
        'widget_id' => $widget->id,
    ])->assertStatus(201)->assertJsonFragment(['widget_id' => $widget->id]);
});

it('places a new widget below the lowest existing widget', function () {
    seedWidgets();
    $user    = actingAsUser();
    $project = createProject($user);
    $widget  = Widget::first();

    // First widget at y=0, h=4 → next should be at y=4
    DashboardWidget::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
        'widget_id'  => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);

    $response = $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets", [
        'widget_id' => $widget->id,
    ])->assertStatus(201);

    expect($response->json('data.grid_y'))->toBe(4);
});

it('returns 422 when widget_id is missing on store', function () {
    seedWidgets();
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets", [])
         ->assertStatus(422);
});

it('returns 422 when widget_id does not exist on store', function () {
    seedWidgets();
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets", [
        'widget_id' => 99999,
    ])->assertStatus(422);
});

it('returns 403 on store when not a member', function () {
    seedWidgets();
    actingAsUser();
    $other   = User::factory()->create();
    $project = createProject($other);
    $widget  = Widget::first();

    $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets", [
        'widget_id' => $widget->id,
    ])->assertStatus(403);
});

// --- Sync ---

it('member can sync dashboard widget layout', function () {
    seedWidgets();
    $user    = actingAsUser();
    $project = createProject($user);
    $widget  = Widget::first();

    $dw = DashboardWidget::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
        'widget_id'  => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);

    $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets/sync", [
        'layout' => [
            ['i' => (string) $dw->id, 'x' => 2, 'y' => 3, 'w' => 6, 'h' => 5],
        ],
    ])->assertStatus(200);

    $dw->refresh();
    expect($dw->grid_x)->toBe(2)
        ->and($dw->grid_y)->toBe(3)
        ->and($dw->grid_w)->toBe(6)
        ->and($dw->grid_h)->toBe(5);
});

it('sync does not update another user\'s widgets', function () {
    seedWidgets();

    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    $widget = Widget::first();

    $ownerDw  = DashboardWidget::factory()->create([
        'project_id' => $project->id, 'user_id' => $owner->id, 'widget_id' => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);
    $memberDw = DashboardWidget::factory()->create([
        'project_id' => $project->id, 'user_id' => $member->id, 'widget_id' => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);

    Sanctum::actingAs($owner);

    // Owner syncs their own widget AND tries to pass member's widget ID
    $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets/sync", [
        'layout' => [
            ['i' => (string) $ownerDw->id,  'x' => 1, 'y' => 1, 'w' => 3, 'h' => 3],
            ['i' => (string) $memberDw->id, 'x' => 9, 'y' => 9, 'w' => 9, 'h' => 9],
        ],
    ])->assertStatus(200);

    // Member's widget should be unchanged
    $memberDw->refresh();
    expect($memberDw->grid_x)->toBe(0)
        ->and($memberDw->grid_y)->toBe(0);
});

it('returns 422 when layout is missing on sync', function () {
    seedWidgets();
    $user    = actingAsUser();
    $project = createProject($user);

    $this->postJson("/api/v1/projects/{$project->id}/dashboard-widgets/sync", [])
         ->assertStatus(422);
});

// --- Destroy ---

it('user can delete their own dashboard widget', function () {
    seedWidgets();
    $user    = actingAsUser();
    $project = createProject($user);
    $widget  = Widget::first();

    $dw = DashboardWidget::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
        'widget_id'  => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);

    $this->deleteJson("/api/v1/dashboard-widgets/{$dw->id}")->assertStatus(200);

    expect(DashboardWidget::find($dw->id))->toBeNull();
});

it('returns 403 when deleting another user\'s dashboard widget', function () {
    seedWidgets();

    ['project' => $project, 'owner' => $owner, 'member' => $member] = createProjectWithMember();
    $widget = Widget::first();

    $ownerDw = DashboardWidget::factory()->create([
        'project_id' => $project->id, 'user_id' => $owner->id, 'widget_id' => $widget->id,
        'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 4,
    ]);

    Sanctum::actingAs($member);

    $this->deleteJson("/api/v1/dashboard-widgets/{$ownerDw->id}")->assertStatus(403);
});

it('returns 401 on destroy when unauthenticated', function () {
    $this->deleteJson('/api/v1/dashboard-widgets/1')->assertStatus(401);
});
