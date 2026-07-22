<?php

namespace Database\Factories;

use App\Models\Embedding;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmbeddingFactory extends Factory
{
    protected $model = Embedding::class;

    public function definition(): array
    {
        return [
            'project_id'   => Project::factory(),
            'source_type'  => 'decision',
            'source_id'    => fake()->numberBetween(1, 10000),
            'content_hash' => hash('sha256', fake()->sentence()),
            // no 'embedding' — the vector column is pgsql-only and set by EmbedRecord
        ];
    }
}
