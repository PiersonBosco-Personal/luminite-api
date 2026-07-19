<?php

namespace Database\Factories;

use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DecisionFactory extends Factory
{
    protected $model = Decision::class;

    public function definition(): array
    {
        return [
            'project_id'       => Project::factory(),
            'task_id'          => null,
            'created_by'       => User::factory(),
            'decision'         => fake()->sentence(),
            'rationale'        => fake()->sentence(),
            'status'           => 'active',
            'superseded_by_id' => null,
        ];
    }
}
