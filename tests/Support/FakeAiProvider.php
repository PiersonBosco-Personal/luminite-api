<?php

namespace Tests\Support;

use App\AI\Contracts\AIProvider;

class FakeAiProvider implements AIProvider
{
    /** Deterministic 1536-dim vector. No network — used everywhere in the suite. */
    public function embed(string $text): array
    {
        return array_fill(0, 1536, 0.001);
    }
}
