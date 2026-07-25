<?php

namespace App\Observers;

use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Embedding;

class DecisionObserver
{
    public function created(Decision $decision): void
    {
        $this->queue($decision);
    }

    public function updated(Decision $decision): void
    {
        if ($decision->wasChanged(['decision', 'rationale'])) {
            $this->queue($decision);
        }
    }

    public function deleted(Decision $decision): void
    {
        Embedding::where('source_type', 'decision')->where('source_id', $decision->id)->delete();
    }

    private function queue(Decision $decision): void
    {
        EmbedRecord::dispatch('decision', $decision->id)
            ->delay(now()->addSeconds(EmbedRecord::DEDUPE_WINDOW_SECONDS));
    }
}
