<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GetOpenTasks extends Tool
{
    public function definition(): array
    {
        return [
            'name' => 'get_open_tasks',
            'description' => 'List open (non-done) tasks. Call this when you need to know what to work on, or to find a task id before updating/completing it. Each task is listed as "#id [priority] title — status"; pass that #id (the number) as task_id to update_task or complete_task. Filter by status, priority, section_id, or label_id. When you mention a task to the user, refer to it by name — use the #id only as the task_id argument.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['todo', 'in_progress', 'done', 'blocked']],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                    'section_id' => ['type' => 'integer'],
                    'label_id' => ['type' => 'integer'],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $query = Task::with(['section', 'assignee', 'labels', 'subtasks' => fn ($q) => $q->orderBy('position')])
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
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderBy('position')
            ->get();

        if ($tasks->isEmpty()) {
            return 'No tasks match the given filters.';
        }

        $lines = ["Tasks ({$tasks->count()}):"];
        foreach ($tasks as $task) {
            // Lead with the numeric id (as #id) so it can be passed straight to
            // update_task / complete_task — those echo it back the same way.
            $parts = ["#{$task->id}", "[{$task->priority}]", $task->title, "— {$task->status}"];

            if ($task->section) {
                $parts[] = "— section: {$task->section->name}";
            }
            if ($task->assignee) {
                $parts[] = "— assigned: {$task->assignee->name}";
            }
            if ($task->labels->isNotEmpty()) {
                $parts[] = '— labels: '.$task->labels->pluck('name')->join(', ');
            }
            if ($task->due_date) {
                $parts[] = "— due: {$task->due_date}";
            }

            $lines[] = '- '.implode(' ', $parts);
            if (filled($task->description)) {
                $lines[] = '    ↳ '.Str::limit((string) $task->description, 140);
            }
            foreach ($task->subtasks as $subtask) {
                $lines[] = "    ↳ subtask: {$subtask->title} — {$subtask->status}";
            }
        }

        return implode("\n", $lines);
    }
}
