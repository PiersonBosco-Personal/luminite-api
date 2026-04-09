<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id'    => User::factory(),
            'name'        => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status'      => 'active',
        ];
    }
}
