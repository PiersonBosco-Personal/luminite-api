<?php

namespace App\AI\Contracts;

interface AIProvider
{
    /**
     * Embed a string into a vector. Returns a float[] (1536 dims for
     * OpenAI text-embedding-3-small). chat() is intentionally not on this
     * interface in Phase 4 — embeddings only (see AI.md).
     *
     * @return float[]
     */
    public function embed(string $text): array;
}
