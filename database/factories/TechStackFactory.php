<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechStackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id'  => null,
            'name'       => fake()->word(),
            'version'    => '1.0.0',
        ];
    }
}
