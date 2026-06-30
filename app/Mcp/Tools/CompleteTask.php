<?php

namespace App\Mcp\Tools;

use App\Events\TaskUpdated;
use App\Mcp\Tools\Concerns\ResolvesTaskInput;
use App\Models\Task;
use App\Models\TaskSection;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompleteTask extends Tool
{
    use ResolvesTaskInput;

    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name' => 'complete_task',
            'description' => 'Mark a task done by id. ALWAYS pass summary (what actually changed, 1-2 sentences) and rationale (why it made sense) so your teammate understands the change without asking you out loud — these become the team changelog. notes is recorded in the activity feed, not on the task body. The task is moved to the project\'s "Done" section by default; pass section (id or name) to move it elsewhere. If no "Done" section exists the task is still completed and the response asks you to pick a section with the user — then call complete_task again with the section argument. The #id/[id] shown is for your tool calls only — never repeat it to the user; refer to items by title. Requires a token with the write scope.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'task_id' => ['type' => 'integer'],
                    'notes' => ['type' => 'string'],
                    'section' => ['type' => ['string', 'integer']],
                    'summary'   => ['type' => 'string'],
                    'rationale' => ['type' => 'string'],
                ],
                'required' => ['task_id'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId = $this->userId($request);
        $taskId = (int) ($args['task_id'] ?? 0);

        $task = Task::where('project_id', $projectId)->whereKey($taskId)->first();
        if (! $task) {
            return "Error: task #{$taskId} not found in this project.";
        }

        $section = $args['section'] ?? null;
        $destination = null;

        if ($section !== null && $section !== '') {
            try {
                $destination = TaskSection::findOrFail($this->resolveSectionId($projectId, $section));
            } catch (ToolInputException $e) {
                return "Error: {$e->getMessage()} Sections: {$this->sectionList($projectId)}";
            }
        } else {
            $destination = TaskSection::where('project_id', $projectId)
                ->whereRaw('LOWER(name) = ?', ['done'])
                ->first();
        }

        DB::transaction(function () use ($task, $destination, $projectId) {
            $updates = ['status' => 'done'];

            if ($destination && $destination->id !== $task->section_id) {
                // Top-of-section: free position 0 in Done, place the task there.
                $this->shiftSectionDown($projectId, $destination->id);
                $updates['section_id'] = $destination->id;
                $updates['position'] = 0;
            }

            $task->update($updates);
        });

        $task->load('assignee', 'labels');

        broadcast(new TaskUpdated($task, $projectId));

        app(\App\Services\TaskCompletionService::class)->record(
            task: $task,
            userId: $userId,
            what: $args['summary'] ?? null,
            why: $args['rationale'] ?? null,
            source: 'claude',
        );

        $notes = trim((string) ($args['notes'] ?? ''));
        $suffix = $notes !== '' ? " — {$notes}" : '';
        $moved = $destination ? " (moved to {$destination->name})" : '';

        app(ActivityLogService::class)->log(
            projectId: $projectId,
            userId: $userId,
            eventType: 'task.completed',
            subjectType: 'task',
            subjectLabel: $task->title,
            subjectId: $task->id,
            description: "completed {$task->title}{$suffix}{$moved}",
            viaMcp: true,
        );

        if (! $destination) {
            return "Completed \"{$task->title}\"{$suffix} — no \"Done\" section exists in this project, so the task was left in its current section. "
                .'Ask the user which section the task should move to, then call complete_task again with the section argument. '
                ."Sections: {$this->sectionList($projectId)}";
        }

        return "Completed \"{$task->title}\"{$suffix}{$moved}.";
    }

    private function sectionList(int $projectId): string
    {
        return TaskSection::where('project_id', $projectId)
            ->orderBy('position')
            ->get()
            ->map(fn (TaskSection $s) => "[{$s->id}] {$s->name}")
            ->implode(', ');
    }
}
