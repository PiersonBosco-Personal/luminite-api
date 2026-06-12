<?php

namespace App\Mcp\Tools;

use App\Events\SectionCreated;
use App\Events\TaskCreated;
use App\Events\TaskUpdated;
use App\Models\Task;
use App\Models\TaskSection;
use App\Services\ActivityLogService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncTodos extends Tool
{
    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'sync_todos',
            'description' => 'Reconcile code TODO/FIXME comments with Luminite tasks. Send files (every file path you scanned this turn) and todos (every TODO/FIXME found across those files, each {text, file, line?}). New todos become tasks in a "Triage" section; already-tracked todos are skipped; a tracked task whose todo has disappeared from a scanned file is auto-completed. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'files' => [
                        'type'        => 'array',
                        'items'       => ['type' => 'string'],
                        'description' => 'Every file path scanned this turn (drives reconciliation of removed todos).',
                    ],
                    'todos' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'text'     => ['type' => 'string'],
                                'file'     => ['type' => 'string'],
                                'line'     => ['type' => 'integer'],
                                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'description' => 'Map FIXME→high, TODO→medium. Defaults to medium.'],
                            ],
                            'required' => ['text', 'file'],
                        ],
                    ],
                ],
                'required' => ['files', 'todos'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);

        $files = array_values(array_filter(array_map('strval', (array) ($args['files'] ?? []))));
        $todos = (array) ($args['todos'] ?? []);

        if ($files === []) {
            return 'Error: files must be a non-empty array of the paths you scanned.';
        }

        $sectionId = $this->triageSectionId($projectId);

        // Index incoming todos by file → set of source hashes.
        $incomingByFile = [];
        foreach ($todos as $todo) {
            $text = trim((string) ($todo['text'] ?? ''));
            $file = (string) ($todo['file'] ?? '');
            if ($text === '' || $file === '') {
                continue;
            }
            $incomingByFile[$file][] = $this->hash($projectId, $file, $text);
        }

        $created = $this->createNew($projectId, $userId, $sectionId, $todos);
        $skipped = $this->countSkipped($projectId, $todos, $created);
        $completed = $this->reconcileRemoved($projectId, $userId, $files, $incomingByFile);

        if ($created > 0 || $completed > 0) {
            app(ActivityLogService::class)->log(
                projectId:    $projectId,
                userId:       $userId,
                eventType:    'task.synced',
                subjectType:  'task',
                subjectLabel: "{$created} created, {$completed} completed",
                description:  "synced todos: created {$created}, completed {$completed}",
                viaMcp:       true,
            );
        }

        return "Synced: created {$created}, skipped {$skipped} (already tracked), completed {$completed} (resolved in code)";
    }

    private function createNew(int $projectId, int $userId, int $sectionId, array $todos): int
    {
        $created = 0;

        foreach ($todos as $todo) {
            $text = trim((string) ($todo['text'] ?? ''));
            $file = (string) ($todo['file'] ?? '');
            if ($text === '' || $file === '') {
                continue;
            }

            $hash = $this->hash($projectId, $file, $text);
            if (Task::where('project_id', $projectId)->where('source_hash', $hash)->exists()) {
                continue;
            }

            $priority = in_array($todo['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true)
                ? $todo['priority']
                : 'medium';

            try {
                $task = DB::transaction(function () use ($projectId, $sectionId, $userId, $text, $file, $hash, $priority) {
                    $position = (Task::where('project_id', $projectId)->where('section_id', $sectionId)->max('position') ?? -1) + 1;

                    return Task::create([
                        'project_id'  => $projectId,
                        'section_id'  => $sectionId,
                        'created_by'  => $userId,
                        'title'       => $text,
                        'status'      => 'todo',
                        'priority'    => $priority,
                        'position'    => $position,
                        'source_hash' => $hash,
                        'source_file' => $file,
                    ]);
                });
            } catch (QueryException $e) {
                continue; // lost a race on unique(project_id, source_hash)
            }

            $task->load('assignee', 'labels');
            broadcast(new TaskCreated($task, $projectId));
            $created++;
        }

        return $created;
    }

    /** @return int Number of valid todos that were skipped because they are already tracked. */
    private function countSkipped(int $projectId, array $todos, int $created): int
    {
        $valid = 0;
        foreach ($todos as $todo) {
            $text = trim((string) ($todo['text'] ?? ''));
            $file = (string) ($todo['file'] ?? '');
            if ($text !== '' && $file !== '') {
                $valid++;
            }
        }

        return max(0, $valid - $created);
    }

    /** @param array<string, array<int, string>> $incomingByFile */
    private function reconcileRemoved(int $projectId, int $userId, array $files, array $incomingByFile): int
    {
        $completed = 0;

        $tracked = Task::where('project_id', $projectId)
            ->whereIn('source_file', $files)
            ->where('status', '!=', 'done')
            ->whereNotNull('source_hash')
            ->get();

        foreach ($tracked as $task) {
            $stillPresent = in_array($task->source_hash, $incomingByFile[$task->source_file] ?? [], true);
            if ($stillPresent) {
                continue;
            }

            $task->update(['status' => 'done']);
            $task->load('assignee', 'labels');
            broadcast(new TaskUpdated($task, $projectId));
            $completed++;
        }

        return $completed;
    }

    private function triageSectionId(int $projectId): int
    {
        $section = TaskSection::where('project_id', $projectId)
            ->whereRaw('LOWER(name) = ?', ['triage'])
            ->first();

        if ($section) {
            return $section->id;
        }

        $position = (int) TaskSection::where('project_id', $projectId)->max('position') + 1;
        $section  = TaskSection::create(['project_id' => $projectId, 'name' => 'Triage', 'position' => $position]);
        broadcast(new SectionCreated($section, $projectId));

        return $section->id;
    }

    private function hash(int $projectId, string $file, string $text): string
    {
        return hash('sha256', "{$projectId}|{$file}|{$text}");
    }
}
