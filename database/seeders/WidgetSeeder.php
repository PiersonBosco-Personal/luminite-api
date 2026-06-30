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
                'slug'        => 'notes_list',
                'name'        => 'Notes',
                'category'    => 'productivity',
                'description' => 'Write daily notes or browse and edit any note in the project.',
                'icon'        => 'notebook-pen',
                'default_w'   => 6,
                'default_h'   => 6,
                'min_w'       => 4,
                'min_h'       => 4,
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
                'slug'        => 'time_tracker',
                'name'        => 'Time Tracker',
                'category'    => 'productivity',
                'description' => 'Start/stop timer and log time against project tasks.',
                'icon'        => 'timer',
                'default_w'   => 4,
                'default_h'   => 7,
                'min_w'       => 3,
                'min_h'       => 5,
            ],
            [
                'slug'        => 'time_report',
                'name'        => 'Time Report',
                'category'    => 'analytics',
                'description' => 'Aggregated time spent by user, work type, or task.',
                'icon'        => 'pie-chart',
                'default_w'   => 6,
                'default_h'   => 5,
                'min_w'       => 4,
                'min_h'       => 4,
            ],
            [
                'slug'        => 'change_log',
                'name'        => 'Changelog',
                'category'    => 'analytics',
                'description' => 'What changed and why on each completed task — by you and your teammate.',
                'icon'        => 'history',
                'default_w'   => 4,
                'default_h'   => 6,
                'min_w'       => 3,
                'min_h'       => 4,
            ],
        ];

        // Stubs that are registered but not yet built/functional. Not offered by
        // initialize_project and shown as "Soon" (grayed) in the widget picker.
        $unavailable = ['ai_chat', 'task_burndown', 'label_breakdown'];

        foreach ($widgets as $widget) {
            DB::table('widgets')->updateOrInsert(
                ['slug' => $widget['slug']],
                array_merge($widget, [
                    'is_active'    => true,
                    'is_available' => ! in_array($widget['slug'], $unavailable, true),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ])
            );
        }
    }
}
