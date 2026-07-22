<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AIProvider
{
    public function __construct(
        private readonly string $key,
        private readonly string $model,
        private readonly string $baseUrl,
    ) {}

    public function embed(string $text): array
    {
        // One HTTP POST — this IS the whole provider. No SDK (spec §5.2).
        $response = Http::withToken($this->key)
            ->post(rtrim($this->baseUrl, '/') . '/embeddings', [
                'model' => $this->model,
                'input' => $text,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI embeddings request failed: HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $embedding = $response->json('data.0.embedding');
        if (! is_array($embedding)) {
            throw new RuntimeException('OpenAI embeddings response missing data.0.embedding: ' . $response->body());
        }

        return $embedding;
    }
}
