<?php

namespace App\Observers;

use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Embedding;

class DecisionObserver
{
    /** A create fires once — no churn to collapse, so index it straight away. */
    public function created(Decision $decision): void
    {
        EmbedRecord::dispatch('decision', $decision->id);
    }

    /** Delayed so the unique lock collapses a burst of edits into one embed call. */
    public function updated(Decision $decision): void
    {
        if ($decision->wasChanged(['decision', 'rationale'])) {
            EmbedRecord::dispatch('decision', $decision->id)
                ->delay(now()->addSeconds(EmbedRecord::DEDUPE_WINDOW_SECONDS));
        }
    }

    public function deleted(Decision $decision): void
    {
        Embedding::where('source_type', 'decision')->where('source_id', $decision->id)->delete();
    }
}
