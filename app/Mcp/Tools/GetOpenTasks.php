<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Http\Request;

class GetOpenTasks extends Tool
{
    public function definition(): array
    {
        return [
            'name'        => 'get_open_tasks',
            'description' => 'Returns tasks filtered by status, priority, section, or label. Excludes done tasks by default. Use mid-session for targeted queries — blocked tasks, urgent items, tasks in a specific section. At session start, use get_session_context instead.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'status'     => ['type' => 'string', 'enum' => ['todo', 'in_progress', 'done', 'blocked']],
                    'priority'   => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                    'section_id' => ['type' => 'integer'],
                    'label_id'   => ['type' => 'integer'],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $query = Task::with(['section', 'assignee', 'labels'])
            ->where('project_id', $this->projectId($request))
            ->whereNull('parent_task_id');

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        } else {
            $query->where('status', '!=', 'done');
        }

        if (isset($args['priority'])) {
            $query->where('priority', $args['priority']);
        }

        if (isset($args['section_id'])) {
            $query->where('section_id', (int) $args['section_id']);
        }

        if (isset($args['label_id'])) {
            $query->whereHas('labels', fn ($q) => $q->where('labels.id', (int) $args['label_id']));
        }

        $tasks = $query
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('position')
            ->get();

        if ($tasks->isEmpty()) {
            return 'No tasks match the given filters.';
        }

        $lines = ["Tasks ({$tasks->count()}):"];
        foreach ($tasks as $task) {
            $parts = ["[{$task->priority}]", $task->title, "— {$task->status}"];

            if ($task->section) {
                $parts[] = "— section: {$task->section->name}";
            }
            if ($task->assignee) {
                $parts[] = "— assigned: {$task->assignee->name}";
            }
            if ($task->labels->isNotEmpty()) {
                $parts[] = '— labels: ' . $task->labels->pluck('name')->join(', ');
            }
            if ($task->due_date) {
                $parts[] = "— due: {$task->due_date}";
            }

            $lines[] = '- ' . implode(' ', $parts);
        }

        return implode("\n", $lines);
    }
}
