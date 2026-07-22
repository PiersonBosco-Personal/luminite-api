<?php

namespace App\Jobs;

use App\AI\Contracts\AIProvider;
use App\Models\Decision;
use App\Models\Embedding;
use App\Models\ThreadEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class EmbedRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Thread-entry types worth embedding. Momentum is deliberately excluded (spec §7). */
    public const EMBEDDABLE_THREAD_TYPES = ['gotcha', 'dead_end'];

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60]; // seconds between retries — ride out transient OpenAI blips
    }

    public function __construct(
        public readonly string $sourceType, // 'decision' | 'thread_entry'
        public readonly int $sourceId,
    ) {}

    public function handle(): void
    {
        $row = match ($this->sourceType) {
            'decision'     => Decision::find($this->sourceId),
            'thread_entry' => ThreadEntry::find($this->sourceId),
            default        => null,
        };

        if (! $row) {
            return; // source deleted before the job ran — nothing to index
        }

        $text = $this->sourceType === 'decision'
            ? $row->decision . "\n" . $row->rationale
            : $row->content;

        $hash = hash('sha256', $text);

        $existing = Embedding::where('source_type', $this->sourceType)
            ->where('source_id', $this->sourceId)
            ->first();

        if ($existing && $existing->content_hash === $hash) {
            return; // unchanged text — already indexed
        }

        $vector = app(AIProvider::class)->embed($text);

        // Must match the vector(1536) column — fail loudly on a malformed provider
        // response instead of a cryptic pgvector INSERT error.
        if (count($vector) !== 1536) {
            throw new \RuntimeException('Expected a 1536-dim embedding, got ' . count($vector) . '.');
        }

        // Vector persistence is Postgres-only. Under SQLite (tests) the column does
        // not exist; the pipeline above is exercised, the write below is skipped.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // floatval on every element guarantees a numeric literal — no injection risk.
        $literal = '[' . implode(',', array_map('floatval', $vector)) . ']';

        Embedding::updateOrCreate(
            ['source_type' => $this->sourceType, 'source_id' => $this->sourceId],
            [
                'project_id'   => $row->project_id,
                'content_hash' => $hash,
                'embedding'    => DB::raw("'{$literal}'::vector"),
            ],
        );
    }
}
