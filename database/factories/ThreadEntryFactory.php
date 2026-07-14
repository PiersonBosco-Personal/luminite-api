<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ThreadEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThreadEntryFactory extends Factory
{
    protected $model = ThreadEntry::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id'    => null,
            'created_by' => User::factory(),
            'type'       => 'momentum',
            'content'    => $this->faker->sentence(),
            'trigger'    => 'manual',
        ];
    }
}
