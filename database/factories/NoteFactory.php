<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'task_id'    => null,
            'title'      => fake()->sentence(4),
            'content'    => null,
            'is_pinned'  => false,
        ];
    }
}
