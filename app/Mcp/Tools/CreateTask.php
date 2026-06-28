<?php

namespace App\Mcp\Tools;

use App\Events\TaskCreated;
use App\Mcp\Tools\Concerns\ResolvesTaskInput;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateTask extends Tool
{
    use ResolvesTaskInput;

    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name' => 'create_task',
            'description' => 'Create a task. Section and labels accept either ids or names; an omitted section defaults to the project\'s first section. Unknown label names are created automatically. Break work down with the subtasks array (an array of titles) — do NOT enumerate steps in the description field; description is for context and acceptance criteria only. Pass parent (a task id) to create this itself as a subtask. New tasks are placed at the top of their section. The #id in responses is for your tool calls only — never repeat it to the user; refer to tasks by title. Requires a token with the write scope.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                    'section' => ['type' => ['string', 'integer']],
                    'labels' => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                    'parent' => ['type' => 'integer', 'description' => 'Optional parent task id — creates this as a subtask.'],
                    'subtasks' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Child task titles. This is the correct home for a step-by-step breakdown — use it instead of listing steps in description.'],
                ],
                'required' => ['title'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId = $this->userId($request);

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '') {
            return 'Error: title is required.';
        }

        try {
            $sectionId = (isset($args['section']) && $args['section'] !== '')
                ? $this->resolveSectionId($projectId, $args['section'])
                : $this->defaultSectionId($projectId);
            $labelIds = $this->resolveLabelIds($projectId, (array) ($args['labels'] ?? []));
        } catch (ToolInputException $e) {
            return 'Error: '.$e->getMessage();
        }

        $priority = in_array($args['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true)
            ? $args['priority']
            : 'medium';

        $parentId = null;
        if (isset($args['parent']) && $args['parent'] !== '') {
            $parentId = (int) $args['parent'];
            $parentExists = Task::where('project_id', $projectId)->whereKey($parentId)->exists();
            if (! $parentExists) {
                return "Error: parent task #{$parentId} not found in this project.";
            }
        }

        $subtaskTitles = $this->normalizeSubtaskTitles($args['subtasks'] ?? null);

        [$task, $children] = DB::transaction(function () use ($projectId, $sectionId, $userId, $title, $args, $priority, $labelIds, $parentId, $subtaskTitles) {
            // Top-of-section insert: free position 0, then create there.
            $this->shiftSectionDown($projectId, $sectionId);

            $task = Task::create([
                'project_id' => $projectId,
                'section_id' => $sectionId,
                'parent_task_id' => $parentId,
                'created_by' => $userId,
                'title' => $title,
                'description' => $args['description'] ?? null,
                'status' => 'todo',
                'priority' => $priority,
                'position' => 0,
            ]);

            if ($labelIds) {
                $task->labels()->sync($labelIds);
            }

            // Subtasks live in the same section, nested under the new task.
            $children = $this->createSubtasks($projectId, $sectionId, $userId, $task->id, $subtaskTitles);

            return [$task, $children];
        });

        $task->load('assignee', 'labels');

        broadcast(new TaskCreated($task, $projectId));

        app(ActivityLogService::class)->log(
            projectId: $projectId,
            userId: $userId,
            eventType: 'task.created',
            subjectType: 'task',
            subjectLabel: $task->title,
            subjectId: $task->id,
            description: "created task {$task->title}",
            viaMcp: true,
        );

        $childNote = $children
            ? ' with '.count($children).' subtask'.(count($children) === 1 ? '' : 's').': '
                .implode(', ', array_map(fn ($c) => "\"{$c->title}\"", $children))
            : '';

        return "Created \"{$task->title}\"{$childNote}.";
    }
}
