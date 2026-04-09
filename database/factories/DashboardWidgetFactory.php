<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DashboardWidgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id'    => User::factory(),
            'widget_id'  => 1, // caller must override; Widget catalog is seeded, not factory-created
            'config'     => null,
            'grid_x'     => 0,
            'grid_y'     => 0,
            'grid_w'     => 4,
            'grid_h'     => 4,
        ];
    }
}
