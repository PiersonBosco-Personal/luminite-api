<?php

namespace App\Observers;

use App\Jobs\EmbedRecord;
use App\Models\Embedding;
use App\Models\Task;

class TaskObserver
{
    public function created(Task $task): void
    {
        $this->queue($task);
    }

    /**
     * Only re-embed when the embedded text actually changed. Drag-reordering a
     * board writes `position` on every card and status changes are constant —
     * neither affects the vector, so neither should cost a job.
     */
    public function updated(Task $task): void
    {
        if ($task->wasChanged(['title', 'description'])) {
            $this->queue($task);
        }
    }

    public function deleted(Task $task): void
    {
        Embedding::where('source_type', 'task')->where('source_id', $task->id)->delete();
    }

    private function queue(Task $task): void
    {
        EmbedRecord::dispatch('task', $task->id)
            ->delay(now()->addSeconds(EmbedRecord::DEDUPE_WINDOW_SECONDS));
    }
}
