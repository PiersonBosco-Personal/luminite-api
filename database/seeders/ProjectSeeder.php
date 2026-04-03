<?php

namespace Database\Seeders;

use App\Models\DashboardWidget;
use App\Models\Label;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\TechStack;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'test@example.com')->first();

        $second = User::factory()->create([
            'name'  => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        // --- Project 1: Web App ---
        $webApp = Project::create([
            'owner_id'    => $owner->id,
            'name'        => 'Luminite Web App',
            'description' => 'The main React frontend for Luminite.',
            'status'      => 'active',
        ]);
        $webApp->members()->attach($owner->id, ['role' => 'owner']);
        $webApp->members()->attach($second->id, ['role' => 'member']);

        TechStack::insert([
            ['project_id' => $webApp->id, 'name' => 'React',      'app_label' => 'frontend', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $webApp->id, 'name' => 'TypeScript',  'app_label' => 'frontend', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $webApp->id, 'name' => 'Tailwind CSS','app_label' => 'frontend', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $webApp->id, 'name' => 'Laravel',     'app_label' => 'backend',  'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $webApp->id, 'name' => 'MySQL',       'app_label' => 'backend',  'created_at' => now(), 'updated_at' => now()],
        ]);

        $bugLabel      = Label::create(['project_id' => $webApp->id, 'name' => 'Bug',      'color' => '#ef4444']);
        $featureLabel  = Label::create(['project_id' => $webApp->id, 'name' => 'Feature',  'color' => '#6366f1']);
        $reviewLabel   = Label::create(['project_id' => $webApp->id, 'name' => 'Review',   'color' => '#f59e0b']);

        $backlog = TaskSection::create(['project_id' => $webApp->id, 'name' => 'Backlog',     'position' => 0]);
        $inProg  = TaskSection::create(['project_id' => $webApp->id, 'name' => 'In Progress', 'position' => 1]);
        $done    = TaskSection::create(['project_id' => $webApp->id, 'name' => 'Done',        'position' => 2]);

        $t1 = Task::create([
            'project_id'  => $webApp->id,
            'section_id'  => $backlog->id,
            'title'       => 'Set up TanStack Query',
            'description' => 'Install and configure TanStack Query for server state management.',
            'status'      => 'todo',
            'priority'    => 'high',
            'position'    => 0,
        ]);
        $t1->labels()->attach($featureLabel->id);

        $t2 = Task::create([
            'project_id'  => $webApp->id,
            'section_id'  => $inProg->id,
            'title'       => 'Build project list page',
            'description' => 'Replace the dashboard shell with a real project list view.',
            'status'      => 'in_progress',
            'priority'    => 'high',
            'assigned_to' => $owner->id,
            'position'    => 0,
        ]);
        $t2->labels()->attach($featureLabel->id);

        $t3 = Task::create([
            'project_id'  => $webApp->id,
            'section_id'  => $inProg->id,
            'title'       => 'Fix 401 redirect loop on token expiry',
            'status'      => 'in_progress',
            'priority'    => 'urgent',
            'assigned_to' => $second->id,
            'position'    => 1,
        ]);
        $t3->labels()->attach($bugLabel->id);

        $t4 = Task::create([
            'project_id'  => $webApp->id,
            'section_id'  => $done->id,
            'title'       => 'Sanctum token auth',
            'status'      => 'done',
            'priority'    => 'high',
            'position'    => 0,
        ]);
        $t4->labels()->attach($featureLabel->id);

        // Subtask
        Task::create([
            'project_id'     => $webApp->id,
            'section_id'     => $backlog->id,
            'parent_task_id' => $t1->id,
            'title'          => 'Create useQuery hooks for projects',
            'status'         => 'todo',
            'priority'       => 'medium',
            'position'       => 0,
        ]);

        Note::create([
            'project_id' => $webApp->id,
            'created_by' => $owner->id,
            'title'      => 'Frontend architecture decisions',
            'content'    => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Using TanStack Query for all server state. React Context only for auth and current project. Local useState for UI-only state.']]]]]),
            'is_pinned'  => true,
        ]);

        Note::create([
            'project_id' => $webApp->id,
            'task_id'    => $t1->id,
            'created_by' => $second->id,
            'title'      => 'TanStack Query setup notes',
            'content'    => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'QueryClient should be initialized in main.tsx and wrapped around the App component.']]]]]),
            'is_pinned'  => false,
        ]);

        DashboardWidget::insert([
            ['project_id' => $webApp->id, 'type' => 'task_summary',    'config' => json_encode(['show_blocked' => true]), 'grid_x' => 0, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $webApp->id, 'type' => 'recent_notes',    'config' => json_encode(['limit' => 5]),            'grid_x' => 4, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $webApp->id, 'type' => 'member_activity', 'config' => null,                                  'grid_x' => 8, 'grid_y' => 0, 'grid_w' => 4, 'grid_h' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // --- Project 2: API ---
        $api = Project::create([
            'owner_id'    => $owner->id,
            'name'        => 'Luminite API',
            'description' => 'Laravel REST API backend.',
            'status'      => 'active',
        ]);
        $api->members()->attach($owner->id, ['role' => 'owner']);

        TechStack::insert([
            ['project_id' => $api->id, 'name' => 'Laravel 11', 'app_label' => 'backend', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $api->id, 'name' => 'MySQL',       'app_label' => 'backend', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $api->id, 'name' => 'Redis',        'app_label' => 'backend', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $apiBacklog = TaskSection::create(['project_id' => $api->id, 'name' => 'Backlog',     'position' => 0]);
        $apiDone    = TaskSection::create(['project_id' => $api->id, 'name' => 'Done',        'position' => 1]);

        $apiLabel = Label::create(['project_id' => $api->id, 'name' => 'API', 'color' => '#10b981']);

        $at1 = Task::create([
            'project_id' => $api->id,
            'section_id' => $apiBacklog->id,
            'title'      => 'Write Laravel Policies for all resources',
            'status'     => 'todo',
            'priority'   => 'high',
            'position'   => 0,
        ]);
        $at1->labels()->attach($apiLabel->id);

        $at2 = Task::create([
            'project_id' => $api->id,
            'section_id' => $apiDone->id,
            'title'      => 'Phase 1: Migrations and models',
            'status'     => 'done',
            'priority'   => 'high',
            'position'   => 0,
        ]);
        $at2->labels()->attach($apiLabel->id);

        Note::create([
            'project_id' => $api->id,
            'created_by' => $owner->id,
            'title'      => 'API conventions',
            'content'    => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'All routes are versioned under /api/v1/. Project-scoped routes require auth:sanctum + project.member middleware.']]]]]),
            'is_pinned'  => true,
        ]);
    }
}
