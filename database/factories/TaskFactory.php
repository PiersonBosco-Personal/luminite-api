<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TaskSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'     => Project::factory(),
            'section_id'     => TaskSection::factory(),
            'parent_task_id' => null,
            'assigned_to'    => null,
            'title'          => fake()->sentence(4),
            'description'    => null,
            'status'         => 'todo',
            'priority'       => 'medium',
            'due_date'       => null,
            'position'       => 0,
        ];
    }
}
