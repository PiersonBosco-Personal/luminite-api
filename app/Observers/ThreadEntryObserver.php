<?php

namespace App\Observers;

use App\Jobs\EmbedRecord;
use App\Models\Embedding;
use App\Models\ThreadEntry;

class ThreadEntryObserver
{
    public function created(ThreadEntry $entry): void
    {
        $this->queue($entry);
    }

    public function updated(ThreadEntry $entry): void
    {
        if ($entry->wasChanged('content')) {
            $this->queue($entry);
        }
    }

    public function deleted(ThreadEntry $entry): void
    {
        Embedding::where('source_type', 'thread_entry')->where('source_id', $entry->id)->delete();
    }

    /** Momentum entries are session breadcrumbs — never indexed (spec §7). */
    private function queue(ThreadEntry $entry): void
    {
        if (! in_array($entry->type, EmbedRecord::EMBEDDABLE_THREAD_TYPES, true)) {
            return;
        }

        EmbedRecord::dispatch('thread_entry', $entry->id)
            ->delay(now()->addSeconds(EmbedRecord::DEDUPE_WINDOW_SECONDS));
    }
}
