<?php

namespace App\Mcp\Tools;

use App\Events\TaskUpdated;
use App\Mcp\Tools\Concerns\ResolvesTaskInput;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateTask extends Tool
{
    use ResolvesTaskInput;

    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'update_task',
            'description' => 'Edit or move an existing task by id. Call this when you start working a task (move it to In Progress), reprioritize, reassign, retitle, edit the description, or re-parent it as a subtask. Pass only the fields you want to change. section accepts an id or name and moves the task (it is appended to the end of the destination). Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'task_id'     => ['type' => 'integer'],
                    'title'       => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status'      => ['type' => 'string', 'enum' => ['todo', 'in_progress', 'done']],
                    'priority'    => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                    'section'     => ['type' => ['string', 'integer'], 'description' => 'Move the task to this section (id or name).'],
                    'due_date'    => ['type' => 'string', 'description' => 'ISO 8601 date, or empty string to clear.'],
                    'assignee_id' => ['type' => 'integer', 'description' => 'User id; must be a member of this project.'],
                    'parent'      => ['type' => ['integer', 'null'], 'description' => 'Parent task id, or null to detach.'],
                    'labels'      => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'Replace the task\'s labels with these (ids or names).'],
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

        $updates  = [];
        $changed  = [];
        $labelIds = null;

        if (array_key_exists('title', $args) && trim((string) $args['title']) !== '') {
            $updates['title'] = trim((string) $args['title']);
            $changed[] = 'title';
        }
        if (array_key_exists('description', $args)) {
            $updates['description'] = $args['description'] !== '' ? $args['description'] : null;
            $changed[] = 'description';
        }
        if (in_array($args['status'] ?? null, ['todo', 'in_progress', 'done'], true)) {
            $updates['status'] = $args['status'];
            $changed[] = 'status';
        }
        if (in_array($args['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true)) {
            $updates['priority'] = $args['priority'];
            $changed[] = 'priority';
        }
        if (array_key_exists('due_date', $args)) {
            $updates['due_date'] = $args['due_date'] !== '' ? $args['due_date'] : null;
            $changed[] = 'due_date';
        }

        if (isset($args['assignee_id']) && $args['assignee_id'] !== '') {
            $assigneeId = (int) $args['assignee_id'];
            $isMember = ProjectMember::where('project_id', $projectId)->where('user_id', $assigneeId)->exists();
            if (! $isMember) {
                return "Error: user #{$assigneeId} is not a member of this project.";
            }
            $updates['assigned_to'] = $assigneeId;
            $changed[] = 'assignee';
        }

        if (array_key_exists('parent', $args)) {
            if ($args['parent'] === null || $args['parent'] === '') {
                $updates['parent_task_id'] = null;
            } else {
                $parentId = (int) $args['parent'];
                if ($parentId === $task->id) {
                    return 'Error: a task cannot be its own parent.';
                }
                if (! Task::where('project_id', $projectId)->whereKey($parentId)->exists()) {
                    return "Error: parent task #{$parentId} not found in this project.";
                }
                $updates['parent_task_id'] = $parentId;
            }
            $changed[] = 'parent';
        }

        if (isset($args['section']) && $args['section'] !== '') {
            try {
                $sectionId = $this->resolveSectionId($projectId, $args['section']);
            } catch (ToolInputException $e) {
                return "Error: {$e->getMessage()}";
            }
            if ($sectionId !== $task->section_id) {
                $updates['section_id'] = $sectionId;
                $updates['position']   = (int) Task::where('section_id', $sectionId)->max('position') + 1;
                $changed[] = 'section';
            }
        }

        if (array_key_exists('labels', $args)) {
            try {
                $labelIds = $this->resolveLabelIds($projectId, (array) $args['labels']);
            } catch (ToolInputException $e) {
                return "Error: {$e->getMessage()}";
            }
            $changed[] = 'labels';
        }

        if (! $updates && $labelIds === null) {
            return "No changes: provide at least one field to update on task #{$task->id}.";
        }

        DB::transaction(function () use ($task, $updates, $labelIds) {
            if ($updates) {
                $task->update($updates);
            }
            if ($labelIds !== null) {
                $task->labels()->sync($labelIds);
            }
        });

        $task->load('assignee', 'labels', 'section');
        broadcast(new TaskUpdated($task, $projectId));

        $changedList = implode(', ', $changed);
        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    'task.updated',
            subjectType:  'task',
            subjectLabel: $task->title,
            subjectId:    $task->id,
            description:  "updated {$task->title} ({$changedList})",
            viaMcp:       true,
        );

        return "Updated task #{$task->id}: {$task->title} — changed: {$changedList}";
    }
}
