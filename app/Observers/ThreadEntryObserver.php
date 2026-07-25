<?php

namespace App\Observers;

use App\Jobs\EmbedRecord;
use App\Models\Embedding;
use App\Models\ThreadEntry;

class ThreadEntryObserver
{
    /** A create fires once — no churn to collapse, so index it straight away. */
    public function created(ThreadEntry $entry): void
    {
        if ($this->embeddable($entry)) {
            EmbedRecord::dispatch('thread_entry', $entry->id);
        }
    }

    /** Delayed so the unique lock collapses a burst of edits into one embed call. */
    public function updated(ThreadEntry $entry): void
    {
        if ($this->embeddable($entry) && $entry->wasChanged('content')) {
            EmbedRecord::dispatch('thread_entry', $entry->id)
                ->delay(now()->addSeconds(EmbedRecord::DEDUPE_WINDOW_SECONDS));
        }
    }

    public function deleted(ThreadEntry $entry): void
    {
        Embedding::where('source_type', 'thread_entry')->where('source_id', $entry->id)->delete();
    }

    /** Momentum entries are session breadcrumbs — never indexed (spec §7). */
    private function embeddable(ThreadEntry $entry): bool
    {
        return in_array($entry->type, EmbedRecord::EMBEDDABLE_THREAD_TYPES, true);
    }
}
