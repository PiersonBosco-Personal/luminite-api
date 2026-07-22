<?php

namespace App\Providers;

use App\AI\Contracts\AIProvider;
use App\AI\Providers\OpenAiProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound lazily — the provider is only constructed when embed() is first
        // called, so a missing key never breaks boot (Rule 6: never hardcode a provider).
        $this->app->bind(AIProvider::class, function () {
            return match (config('ai.provider')) {
                'openai' => new OpenAiProvider(
                    (string) config('ai.openai.key'),
                    (string) config('ai.openai.embed_model'),
                    (string) config('ai.openai.base_url'),
                ),
                default => throw new RuntimeException('Unsupported AI provider: ' . config('ai.provider')),
            };
        });
    }
}
