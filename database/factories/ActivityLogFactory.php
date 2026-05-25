<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'project_id'    => Project::factory(),
            'user_id'       => User::factory(),
            'event_type'    => $this->faker->randomElement(['task.completed', 'task.created', 'section.created', 'label.created', 'tech_stack.added']),
            'subject_type'  => 'task',
            'subject_id'    => $this->faker->numberBetween(1, 100),
            'subject_label' => $this->faker->words(3, true),
            'description'   => $this->faker->sentence(),
            'old_value'     => null,
            'new_value'     => null,
            'field_changed' => null,
            'via_mcp'       => false,
            'debounce_key'  => null,
        ];
    }
}
