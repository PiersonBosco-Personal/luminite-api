<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        return [
            'project_id'       => Project::factory(),
            'task_id'          => Task::factory(),
            'user_id'          => User::factory(),
            'work_type_id'     => WorkType::factory(),
            'description'      => fake()->optional()->sentence(),
            'duration_minutes' => fake()->numberBetween(15, 240),
            'started_at'       => null,
            'stopped_at'       => null,
            'logged_at'        => today(),
        ];
    }

    public function running(): self
    {
        return $this->state(fn () => [
            'duration_minutes' => null,
            'started_at'       => now()->subMinutes(15),
            'stopped_at'       => null,
        ]);
    }
}
