<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskCompletionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id'              => Task::factory(),
            'completed_by_user_id' => User::factory(),
            'summary_what'         => $this->faker->sentence(),
            'summary_why'          => $this->faker->sentence(),
            'source'               => 'claude',
        ];
    }
}
