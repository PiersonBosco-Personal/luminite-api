<?php

namespace App\Mcp\Tools;

use App\Models\ActivityLog;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskSection;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GetSessionContext extends Tool
{
    private const MAX_OPEN_TASKS = 25;

    public function definition(): array
    {
        return [
            'name'        => 'get_session_context',
            'description' => 'Call this at the start of every session before responding to the user. Returns a complete project snapshot: goals, tech stack, open and in-progress tasks, and activity from the last 48 hours. Do not call the granular tools at session start — this covers everything.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => new \stdClass(),
                'required'   => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);

        $project = Project::with([
            'techStacks' => fn ($q) => $q->whereNull('parent_id')->with('children'),
        ])->find($projectId);

        if (! $project) {
            return 'Error: the project associated with this token no longer exists.';
        }

        // Project info
        $lines = ["Project: {$project->name}"];

        if ($project->description) {
            $lines[] = "Description: {$project->description}";
        }

        $lines[] = "Status: {$project->status}";

        if ($project->goals) {
            $lines[] = "Goals: {$project->goals}";
        }

        if ($project->architecture_notes) {
            $lines[] = "Architecture Notes: {$project->architecture_notes}";
        }

        // Tech stack
        if ($project->techStacks->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Tech Stack:';
            foreach ($project->techStacks as $stack) {
                $entry = "- {$stack->name}";
                if ($stack->version) {
                    $entry .= " ({$stack->version})";
                }
                $lines[] = $entry;
                foreach ($stack->children as $child) {
                    $childEntry = "  - {$child->name}";
                    if ($child->version) {
                        $childEntry .= " ({$child->version})";
                    }
                    $lines[] = $childEntry;
                }
            }
        }

        // Sections (with ids — so Claude can target them in write tools)
        $sections = TaskSection::where('project_id', $projectId)->orderBy('position')->get(['id', 'name']);
        if ($sections->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Sections:';
            foreach ($sections as $section) {
                $lines[] = "- [{$section->id}] {$section->name}";
            }
        }

        // Labels (with ids + color)
        $labels = Label::where('project_id', $projectId)->orderBy('name')->get(['id', 'name', 'color']);
        if ($labels->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Labels:';
            foreach ($labels as $label) {
                $color = $label->color ? " ({$label->color})" : '';
                $lines[] = "- [{$label->id}] {$label->name}{$color}";
            }
        }

        // Open tasks (todo + in_progress only, no subtasks)
        $tasks = Task::with(['section', 'assignee', 'labels'])
            ->where('project_id', $projectId)
            ->whereNull('parent_task_id')
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderBy('position')
            ->get();

        $lines[] = '';
        if ($tasks->isEmpty()) {
            $lines[] = 'Open Tasks: none';
        } else {
            $lines[] = "Open Tasks ({$tasks->count()}):";
            foreach ($tasks->take(self::MAX_OPEN_TASKS) as $task) {
                // Lead with the numeric id (as #id) so Claude can target the task
                // directly with update_task / complete_task.
                $parts = ["#{$task->id}", "[{$task->priority}]", $task->title, "— {$task->status}"];

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

            $overflow = $tasks->count() - self::MAX_OPEN_TASKS;
            if ($overflow > 0) {
                $lines[] = "… +{$overflow} more (use get_open_tasks to see all)";
            }
        }

        // Recent activity (last 48h, capped at 20)
        $since   = Carbon::now()->subHours(48);
        $entries = ActivityLog::where('project_id', $projectId)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();

        $lines[] = '';
        if ($entries->isEmpty()) {
            $lines[] = 'Recent Activity: none in the last 48 hours';
        } else {
            $lines[] = 'Recent Activity (last 48h):';
            foreach ($entries as $entry) {
                $viaMcp  = $entry->via_mcp ? ' [via MCP]' : '';
                $lines[] = "{$entry->created_at->toDateTimeString()} — {$entry->description}{$viaMcp}";
            }
        }

        return implode("\n", $lines);
    }
}
