<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WidgetSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = [
            [
                'slug'        => 'tasks_board',
                'name'        => 'Task Board',
                'category'    => 'productivity',
                'description' => 'Kanban-style board filtered by section and status.',
                'icon'        => 'kanban',
                'default_w'   => 8,
                'default_h'   => 6,
                'min_w'       => 8,
                'min_h'       => 6,
            ],
            [
                'slug'        => 'tasks_list',
                'name'        => 'Task List',
                'category'    => 'productivity',
                'description' => 'Flat list of tasks with filters for assignee, priority, and due date.',
                'icon'        => 'list',
                'default_w'   => 6,
                'default_h'   => 5,
                'min_w'       => 3,
                'min_h'       => 3,
            ],
            [
                'slug'        => 'notes_list',
                'name'        => 'Notes',
                'category'    => 'productivity',
                'description' => 'Recent notes with quick-open and search.',
                'icon'        => 'file-text',
                'default_w'   => 4,
                'default_h'   => 6,
                'min_w'       => 3,
                'min_h'       => 3,
            ],
            [
                'slug'        => 'activity_feed',
                'name'        => 'Activity Feed',
                'category'    => 'analytics',
                'description' => 'Live feed of recent changes by any team member.',
                'icon'        => 'activity',
                'default_w'   => 4,
                'default_h'   => 5,
                'min_w'       => 3,
                'min_h'       => 3,
            ],
            [
                'slug'        => 'tech_stack',
                'name'        => 'Tech Stack',
                'category'    => 'productivity',
                'description' => "Overview of the project's registered tech stack.",
                'icon'        => 'layers',
                'default_w'   => 4,
                'default_h'   => 4,
                'min_w'       => 3,
                'min_h'       => 3,
            ],
            [
                'slug'        => 'team_presence',
                'name'        => 'Team',
                'category'    => 'team',
                'description' => 'Team members and their real-time online presence.',
                'icon'        => 'users',
                'default_w'   => 3,
                'default_h'   => 3,
                'min_w'       => 2,
                'min_h'       => 2,
            ],
            [
                'slug'        => 'ai_chat',
                'name'        => 'AI Assistant',
                'category'    => 'ai',
                'description' => 'AI assistant panel scoped to this project.',
                'icon'        => 'bot',
                'default_w'   => 4,
                'default_h'   => 8,
                'min_w'       => 3,
                'min_h'       => 5,
            ],
            [
                'slug'        => 'task_burndown',
                'name'        => 'Burndown Chart',
                'category'    => 'analytics',
                'description' => 'Task completion rate over time.',
                'icon'        => 'trending-down',
                'default_w'   => 6,
                'default_h'   => 5,
                'min_w'       => 4,
                'min_h'       => 4,
            ],
            [
                'slug'        => 'deadline_tracker',
                'name'        => 'Deadlines',
                'category'    => 'productivity',
                'description' => 'Upcoming due dates across all tasks.',
                'icon'        => 'calendar',
                'default_w'   => 4,
                'default_h'   => 5,
                'min_w'       => 3,
                'min_h'       => 3,
            ],
            [
                'slug'        => 'label_breakdown',
                'name'        => 'Label Breakdown',
                'category'    => 'analytics',
                'description' => 'Distribution of labels across tasks.',
                'icon'        => 'tag',
                'default_w'   => 4,
                'default_h'   => 4,
                'min_w'       => 3,
                'min_h'       => 3,
            ],
            [
                'slug'        => 'daily_notes',
                'name'        => 'Daily Notes',
                'category'    => 'productivity',
                'description' => 'Auto-creates a dated note for each day, stored in the Daily Notes folder.',
                'icon'        => 'notebook-pen',
                'default_w'   => 6,
                'default_h'   => 6,
                'min_w'       => 4,
                'min_h'       => 4,
            ],
        ];

        foreach ($widgets as $widget) {
            DB::table('widgets')->updateOrInsert(
                ['slug' => $widget['slug']],
                array_merge($widget, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
