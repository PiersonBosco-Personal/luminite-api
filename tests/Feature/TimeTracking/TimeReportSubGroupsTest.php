<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;

beforeEach(function () {
    $this->owner   = actingAsUser();
    $this->project = createProject($this->owner);
});

function reportUrl(Project $project, array $params = []): string
{
    $query = http_build_query(array_merge([
        'group_by' => 'user',
        'from'     => '2026-05-01',
        'to'       => '2026-05-31',
    ], $params));
    return "/api/v1/projects/{$project->id}/time-entries/report?{$query}";
}

it('includes sub_groups for each user when group_by=user', function () {
    $dev = WorkType::factory()->create([
        'project_id' => $this->project->id, 'name' => 'Development', 'color' => 'blue',
    ]);
    $testing = WorkType::factory()->create([
        'project_id' => $this->project->id, 'name' => 'Testing', 'color' => 'green',
    ]);

    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $dev->id, 'duration_minutes' => 120, 'logged_at' => '2026-05-15',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $testing->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-15',
    ]);

    $response = $this->getJson(reportUrl($this->project));

    $response->assertOk();
    $groups = $response->json('groups');
    expect($groups)->toHaveCount(1);
    expect($groups[0]['id'])->toBe($this->owner->id);
    expect($groups[0]['sub_groups'])->toHaveCount(2);
});

it('sub_groups carry color slug and label from work_type', function () {
    $dev = WorkType::factory()->create([
        'project_id' => $this->project->id, 'name' => 'Development', 'color' => 'blue',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $dev->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-15',
    ]);

    $sub = $this->getJson(reportUrl($this->project))->json('groups.0.sub_groups.0');

    expect($sub['id'])->toBe($dev->id);
    expect($sub['label'])->toBe('Development');
    expect($sub['color'])->toBe('blue');
    expect($sub['minutes'])->toBe(60);
    expect($sub['percent'])->toBe(100);
});

it('entries with null work_type appear as "No work type" sub-group', function () {
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => null, 'duration_minutes' => 30, 'logged_at' => '2026-05-15',
    ]);

    $sub = $this->getJson(reportUrl($this->project))->json('groups.0.sub_groups.0');

    expect($sub['id'])->toBeNull();
    expect($sub['label'])->toBe('No work type');
    expect($sub['color'])->toBeNull();
});

it('sub_groups percentages are scoped to user total, not project total', function () {
    $other = User::factory()->create();
    addMemberToProject($this->project, $other);

    $dev = WorkType::factory()->create([
        'project_id' => $this->project->id, 'name' => 'Development', 'color' => 'blue',
    ]);

    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $dev->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-15',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $other->id,
        'work_type_id' => $dev->id, 'duration_minutes' => 180, 'logged_at' => '2026-05-15',
    ]);

    $groups     = $this->getJson(reportUrl($this->project))->json('groups');
    $ownerGroup = collect($groups)->firstWhere('id', $this->owner->id);

    expect($ownerGroup['sub_groups'][0]['percent'])->toBe(100);
});

it('sub_groups are ordered by minutes desc, label asc on ties', function () {
    $a = WorkType::factory()->create(['project_id' => $this->project->id, 'name' => 'Alpha',   'color' => 'blue']);
    $b = WorkType::factory()->create(['project_id' => $this->project->id, 'name' => 'Bravo',   'color' => 'green']);
    $c = WorkType::factory()->create(['project_id' => $this->project->id, 'name' => 'Charlie', 'color' => 'red']);

    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $a->id, 'duration_minutes' => 30, 'logged_at' => '2026-05-15',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $b->id, 'duration_minutes' => 30, 'logged_at' => '2026-05-15',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $c->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-15',
    ]);

    $subs = $this->getJson(reportUrl($this->project))->json('groups.0.sub_groups');

    expect($subs[0]['label'])->toBe('Charlie');
    expect($subs[1]['label'])->toBe('Alpha');
    expect($subs[2]['label'])->toBe('Bravo');
});

it('excludes running timers from sub_groups', function () {
    $dev = WorkType::factory()->create([
        'project_id' => $this->project->id, 'name' => 'Development', 'color' => 'blue',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $dev->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-15',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $dev->id, 'duration_minutes' => null,
        'started_at' => '2026-05-15 10:00:00', 'logged_at' => '2026-05-15',
    ]);

    $sub = $this->getJson(reportUrl($this->project))->json('groups.0.sub_groups.0');
    expect($sub['minutes'])->toBe(60);
});

it('returns sub_groups: null for group_by=work_type', function () {
    $dev = WorkType::factory()->create([
        'project_id' => $this->project->id, 'name' => 'Development', 'color' => 'blue',
    ]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'work_type_id' => $dev->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-15',
    ]);

    $groups = $this->getJson(reportUrl($this->project, ['group_by' => 'work_type']))->json('groups');
    expect($groups[0])->toHaveKey('sub_groups');
    expect($groups[0]['sub_groups'])->toBeNull();
});

it('returns sub_groups: null for group_by=task', function () {
    $task = Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id]);
    TimeEntry::factory()->create([
        'project_id' => $this->project->id, 'user_id' => $this->owner->id,
        'task_id' => $task->id, 'duration_minutes' => 60, 'logged_at' => '2026-05-15',
    ]);

    $groups = $this->getJson(reportUrl($this->project, ['group_by' => 'task']))->json('groups');
    expect($groups[0]['sub_groups'])->toBeNull();
});

it('returns valid shape for empty date range', function () {
    $response = $this->getJson(reportUrl($this->project));

    $response->assertOk();
    expect($response->json('groups'))->toBe([]);
    expect($response->json('total_minutes'))->toBe(0);
});
