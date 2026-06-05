<?php

namespace App\Mcp\Tools;

use App\Events\TaskUpdated;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class CompleteTask extends Tool
{
    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'complete_task',
            'description' => 'Mark a task done by id, with optional completion notes. Notes are recorded in the activity feed, not on the task body. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'task_id' => ['type' => 'integer'],
                    'notes'   => ['type' => 'string'],
                ],
                'required' => ['task_id'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);
        $taskId    = (int) ($args['task_id'] ?? 0);

        $task = Task::where('project_id', $projectId)->whereKey($taskId)->first();
        if (! $task) {
            return "Error: task #{$taskId} not found in this project.";
        }

        $task->update(['status' => 'done']);
        $task->load('assignee', 'labels');

        broadcast(new TaskUpdated($task, $projectId));

        $notes  = trim((string) ($args['notes'] ?? ''));
        $suffix = $notes !== '' ? " — {$notes}" : '';

        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    'task.completed',
            subjectType:  'task',
            subjectLabel: $task->title,
            subjectId:    $task->id,
            description:  "completed {$task->title}{$suffix}",
            viaMcp:       true,
        );

        return "Completed task #{$task->id}: {$task->title}{$suffix}";
    }
}
