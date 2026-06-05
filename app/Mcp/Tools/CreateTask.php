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
            'name'        => 'create_task',
            'description' => 'Create a task. Section and labels accept either ids or names; an omitted section defaults to the first section. Unknown label names are created automatically. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'title'       => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'priority'    => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                    'section'     => ['type' => ['string', 'integer']],
                    'labels'      => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                ],
                'required' => ['title'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '') {
            return 'Error: title is required.';
        }

        try {
            $sectionId = $this->resolveSectionId($projectId, $args['section'] ?? null);
            $labelIds  = $this->resolveLabelIds($projectId, (array) ($args['labels'] ?? []));
        } catch (ToolInputException $e) {
            return 'Error: ' . $e->getMessage();
        }

        $priority = in_array($args['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true)
            ? $args['priority']
            : 'medium';

        $task = DB::transaction(function () use ($projectId, $sectionId, $userId, $title, $args, $priority, $labelIds) {
            $position = (Task::where('project_id', $projectId)->where('section_id', $sectionId)->max('position') ?? -1) + 1;

            $task = Task::create([
                'project_id'  => $projectId,
                'section_id'  => $sectionId,
                'created_by'  => $userId,
                'title'       => $title,
                'description' => $args['description'] ?? null,
                'status'      => 'todo',
                'priority'    => $priority,
                'position'    => $position,
            ]);

            if ($labelIds) {
                $task->labels()->sync($labelIds);
            }

            return $task;
        });

        $task->load('assignee', 'labels');

        broadcast(new TaskCreated($task, $projectId));

        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    'task.created',
            subjectType:  'task',
            subjectLabel: $task->title,
            subjectId:    $task->id,
            description:  "created task {$task->title}",
            viaMcp:       true,
        );

        return "Created task #{$task->id}: {$task->title}";
    }
}
