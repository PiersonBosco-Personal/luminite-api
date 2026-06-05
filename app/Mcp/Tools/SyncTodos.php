<?php

namespace App\Mcp\Tools;

use App\Events\TaskCreated;
use App\Mcp\Tools\Concerns\ResolvesTaskInput;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncTodos extends Tool
{
    use ResolvesTaskInput;

    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'sync_todos',
            'description' => 'Create tasks from code TODO/FIXME comments. Each todo is {text, file?, line?}. Already-tracked todos (matched by file + text) are skipped. New tasks land in the first section. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'todos' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'text' => ['type' => 'string'],
                                'file' => ['type' => 'string'],
                                'line' => ['type' => 'integer'],
                            ],
                            'required' => ['text'],
                        ],
                    ],
                ],
                'required' => ['todos'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);

        $todos = $args['todos'] ?? [];
        if (! is_array($todos) || $todos === []) {
            return 'Error: todos must be a non-empty array.';
        }

        try {
            $sectionId = $this->defaultSectionId($projectId);
        } catch (ToolInputException $e) {
            return 'Error: ' . $e->getMessage();
        }

        $created = 0;
        $skipped = 0;

        foreach ($todos as $todo) {
            $text = trim((string) ($todo['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $file = (string) ($todo['file'] ?? '');
            $hash = hash('sha256', "{$projectId}|{$file}|{$text}");

            if (Task::where('project_id', $projectId)->where('source_hash', $hash)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $task = DB::transaction(function () use ($projectId, $sectionId, $userId, $text, $hash) {
                    $position = (Task::where('project_id', $projectId)->where('section_id', $sectionId)->max('position') ?? -1) + 1;

                    return Task::create([
                        'project_id'  => $projectId,
                        'section_id'  => $sectionId,
                        'created_by'  => $userId,
                        'title'       => $text,
                        'status'      => 'todo',
                        'priority'    => 'medium',
                        'position'    => $position,
                        'source_hash' => $hash,
                    ]);
                });
            } catch (QueryException $e) {
                // Lost a race on the unique(project_id, source_hash) constraint.
                $skipped++;
                continue;
            }

            $task->load('assignee', 'labels');
            broadcast(new TaskCreated($task, $projectId));
            $created++;
        }

        if ($created > 0) {
            app(ActivityLogService::class)->log(
                projectId:    $projectId,
                userId:       $userId,
                eventType:    'task.synced',
                subjectType:  'task',
                subjectLabel: "{$created} todos",
                description:  "synced {$created} todos from code",
                viaMcp:       true,
            );
        }

        return "Synced: created {$created}, skipped {$skipped} (already tracked)";
    }
}
