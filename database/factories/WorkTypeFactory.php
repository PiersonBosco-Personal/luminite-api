<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<WorkType>
 */
class WorkTypeFactory extends Factory
{
    protected $model = WorkType::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name'       => fake()->randomElement(['Development', 'Testing', 'Design', 'Meeting', 'Documentation', 'Other']),
            'is_active'  => true,
        ];
    }
}
