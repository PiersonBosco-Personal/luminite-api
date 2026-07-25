<?php

namespace App\Observers;

use App\Jobs\EmbedRecord;
use App\Models\Embedding;
use App\Models\Task;

class TaskObserver
{
    /** A create fires once — no churn to collapse, so index it straight away. */
    public function created(Task $task): void
    {
        EmbedRecord::dispatch('task', $task->id);
    }

    /**
     * Only re-embed when the embedded text actually changed. Drag-reordering a
     * board writes `position` on every card and status changes are constant —
     * neither affects the vector, so neither should cost a job.
     *
     * Edits arrive in bursts, so this one is delayed: the unique lock collapses
     * a whole editing session into a single embed call.
     */
    public function updated(Task $task): void
    {
        if ($task->wasChanged(['title', 'description'])) {
            EmbedRecord::dispatch('task', $task->id)
                ->delay(now()->addSeconds(EmbedRecord::DEDUPE_WINDOW_SECONDS));
        }
    }

    public function deleted(Task $task): void
    {
        Embedding::where('source_type', 'task')->where('source_id', $task->id)->delete();
    }
}
