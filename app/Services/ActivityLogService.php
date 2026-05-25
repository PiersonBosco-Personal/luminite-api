<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogService
{
    public function log(
        int     $projectId,
        int     $userId,
        string  $eventType,
        string  $subjectType,
        string  $subjectLabel,
        ?int    $subjectId    = null,
        string  $description  = '',
        ?string $oldValue     = null,
        ?string $newValue     = null,
        ?string $fieldChanged = null,
        bool    $viaMcp       = false,
    ): ActivityLog {
        if ($fieldChanged && $subjectId) {
            $debounceKey = "{$userId}:{$eventType}:{$subjectId}:{$fieldChanged}";

            $existing = ActivityLog::where('project_id', $projectId)
                ->where('user_id', $userId)
                ->where('debounce_key', $debounceKey)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->latest()
                ->first();

            if ($existing) {
                $existing->update([
                    'new_value'   => $newValue,
                    'description' => $description,
                ]);
                return $existing;
            }
        }

        $log = ActivityLog::create([
            'project_id'    => $projectId,
            'user_id'       => $userId,
            'event_type'    => $eventType,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'subject_label' => $subjectLabel,
            'description'   => $description,
            'old_value'     => $oldValue,
            'new_value'     => $newValue,
            'field_changed' => $fieldChanged,
            'via_mcp'       => $viaMcp,
            'debounce_key'  => $fieldChanged && $subjectId
                                ? "{$userId}:{$eventType}:{$subjectId}:{$fieldChanged}"
                                : null,
        ]);

        $log->load('user');

        // broadcast(new \App\Events\ActivityCreated($log, $projectId));
        // TODO: uncomment in Task 3 when ActivityCreated event is created

        return $log;
    }
}
