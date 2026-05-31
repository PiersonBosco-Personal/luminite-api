<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class McpTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'project_id'    => Project::factory(),
            'name'          => fake()->words(3, true),
            'token'         => hash('sha256', fake()->uuid()),
            'scopes'        => ['read'],
            'last_used_at'  => null,
            'request_count' => 0,
            'expires_at'    => null,
        ];
    }
}
