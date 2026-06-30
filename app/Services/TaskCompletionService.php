<?php

namespace App\Services;

use App\Events\TaskCompletionRecorded;
use App\Models\Task;
use App\Models\TaskCompletion;

class TaskCompletionService
{
    /**
     * Append an immutable completion record for a task and broadcast it.
     * Activity logging is intentionally left to the caller (both callers
     * already log task.completed with caller-specific copy).
     */
    public function record(Task $task, int $userId, ?string $what, ?string $why, string $source): TaskCompletion
    {
        $completion = TaskCompletion::create([
            'task_id'              => $task->id,
            'completed_by_user_id' => $userId,
            'summary_what'         => $this->nullIfBlank($what),
            'summary_why'          => $this->nullIfBlank($why),
            'source'               => $source,
        ]);

        broadcast(new TaskCompletionRecorded($completion, $task->project_id));

        return $completion;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
