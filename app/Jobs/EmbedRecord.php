<?php

namespace App\Jobs;

use App\AI\Contracts\AIProvider;
use App\Models\Decision;
use App\Models\Embedding;
use App\Models\Task;
use App\Models\ThreadEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class EmbedRecord implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Thread-entry types worth embedding. Momentum is deliberately excluded (spec §7). */
    public const EMBEDDABLE_THREAD_TYPES = ['gotcha', 'dead_end'];

    public int $tries = 3;

    /**
     * Collapse edit churn. Records are edited far more often than they settle —
     * a note autosaves every 1.5s — so only one pending job per record may exist
     * at a time. Combined with the 5-minute delayed dispatch in the observers,
     * an editing session costs one embed call instead of hundreds.
     */
    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return "{$this->sourceType}:{$this->sourceId}";
    }

    public function backoff(): array
    {
        return [10, 30, 60]; // seconds between retries — ride out transient OpenAI blips
    }

    public function __construct(
        public readonly string $sourceType, // 'decision' | 'thread_entry' | 'task'
        public readonly int $sourceId,
    ) {}

    public function handle(): void
    {
        $row = match ($this->sourceType) {
            'decision'     => Decision::find($this->sourceId),
            'thread_entry' => ThreadEntry::find($this->sourceId),
            'task'         => Task::find($this->sourceId),
            default        => null,
        };

        if (! $row) {
            return; // source deleted before the job ran — nothing to index
        }

        $text = match ($this->sourceType) {
            'decision'     => $row->decision . "\n" . $row->rationale,
            'thread_entry' => $row->content,
            'task'         => trim($row->title . "\n" . ($row->description ?? '')),
        };

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
