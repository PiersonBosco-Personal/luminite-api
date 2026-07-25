<?php

namespace App\Console\Commands;

use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Embedding;
use App\Models\Task;
use App\Models\ThreadEntry;
use Illuminate\Console\Command;

class EmbedBackfill extends Command
{
    protected $signature = 'luminite:embed-backfill {--project= : Restrict the backfill to one project id}';

    protected $description = 'Queue embedding jobs for records that are missing an embedding or whose text has changed since it was indexed.';

    public function handle(): int
    {
        $projectId = $this->option('project');

        // ponytail: loads each table into memory to hash it. Fine at two-developer
        // scale; switch to chunkById if a project ever holds six figures of rows.
        $queued = 0;

        $queued += $this->sweep(
            'decision',
            Decision::query()->when($projectId, fn ($q) => $q->where('project_id', $projectId))->get(),
            fn (Decision $d) => $d->decision . "\n" . $d->rationale,
        );

        $queued += $this->sweep(
            'thread_entry',
            ThreadEntry::query()
                ->whereIn('type', EmbedRecord::EMBEDDABLE_THREAD_TYPES)
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->get(),
            fn (ThreadEntry $e) => $e->content,
        );

        $queued += $this->sweep(
            'task',
            Task::query()->when($projectId, fn ($q) => $q->where('project_id', $projectId))->get(),
            fn (Task $t) => trim($t->title . "\n" . ($t->description ?? '')),
        );

        $this->info("Queued {$queued} embedding job(s).");

        return self::SUCCESS;
    }

    /**
     * Queue a job for every row whose current text hash differs from what is
     * indexed. Idempotent — a second run right after the first queues nothing.
     */
    private function sweep(string $sourceType, iterable $rows, callable $text): int
    {
        $indexed = Embedding::where('source_type', $sourceType)
            ->pluck('content_hash', 'source_id');

        $queued = 0;

        foreach ($rows as $row) {
            if (($indexed[$row->id] ?? null) === hash('sha256', $text($row))) {
                continue;
            }

            EmbedRecord::dispatch($sourceType, $row->id);
            $queued++;
        }

        return $queued;
    }
}
