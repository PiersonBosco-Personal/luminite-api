<?php

namespace App\Mcp\Tools;

use App\Events\LabelCreated;
use App\Events\NoteUpdated;
use App\Events\TaskUpdated;
use App\Mcp\Tools\Concerns\ResolvesTaskInput;
use App\Models\Label;
use App\Models\Note;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ManageLabel extends Tool
{
    use ResolvesTaskInput;

    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'manage_label',
            'description' => 'Create a label, or attach/detach a label to a task or note. action="create" needs name (and optional color hex); action="attach"/"detach" need label (id or name) and exactly one of task_id or note_id. The #id/[id] shown is for your tool calls only — never repeat it to the user; refer to items by title. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'action'  => ['type' => 'string', 'enum' => ['create', 'attach', 'detach']],
                    'name'    => ['type' => 'string'],
                    'color'   => ['type' => 'string', 'description' => 'Hex color like #ef4444 (create only).'],
                    'label'   => ['type' => ['string', 'integer'], 'description' => 'Label id or name (attach/detach).'],
                    'task_id' => ['type' => 'integer'],
                    'note_id' => ['type' => 'integer'],
                ],
                'required' => ['action'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);
        $action    = $args['action'] ?? '';

        if ($action === 'create') {
            $name = trim((string) ($args['name'] ?? ''));
            if ($name === '') {
                return 'Error: name is required to create a label.';
            }
            $color = is_string($args['color'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $args['color'])
                ? strtolower($args['color'])
                : '#94a3b8';

            $label = Label::create(['project_id' => $projectId, 'name' => $name, 'color' => $color]);
            broadcast(new LabelCreated($label, $projectId));

            app(ActivityLogService::class)->log(
                projectId:    $projectId,
                userId:       $userId,
                eventType:    'label.created',
                subjectType:  'label',
                subjectLabel: $label->name,
                subjectId:    $label->id,
                description:  "created label {$label->name}",
                viaMcp:       true,
            );

            return "Created label #{$label->id}: {$label->name}";
        }

        if (! in_array($action, ['attach', 'detach'], true)) {
            return 'Error: action must be one of create, attach, detach.';
        }

        $labelIds = $this->resolveLabelIds($projectId, [$args['label'] ?? '']);
        if ($labelIds === []) {
            return 'Error: provide an existing label id, or a label name to find-or-create.';
        }
        $labelId = $labelIds[0];

        [$model, $kind] = $this->resolveSubject($projectId, $args);
        if (! $model) {
            return $kind; // error string
        }

        if ($action === 'attach') {
            $model->labels()->syncWithoutDetaching([$labelId]);
            $this->broadcastSubjectUpdated($model, $projectId);
            app(ActivityLogService::class)->log(
                projectId:    $projectId,
                userId:       $userId,
                eventType:    'label.attached',
                subjectType:  'label',
                subjectLabel: "label #{$labelId}",
                subjectId:    $labelId,
                description:  "attached label #{$labelId} to {$kind}",
                viaMcp:       true,
            );
            return "Attached label #{$labelId} to {$kind}.";
        }

        $model->labels()->detach([$labelId]);
        $this->broadcastSubjectUpdated($model, $projectId);
        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    'label.detached',
            subjectType:  'label',
            subjectLabel: "label #{$labelId}",
            subjectId:    $labelId,
            description:  "detached label #{$labelId} from {$kind}",
            viaMcp:       true,
        );
        return "Detached label #{$labelId} from {$kind}.";
    }

    /**
     * Broadcast the appropriate "updated" event after a label pivot change so
     * any open board/note view refetches the affected entity with its new labels.
     * The events' broadcastWith() reloads the labels relation, so the payload
     * always reflects the post-change set.
     */
    private function broadcastSubjectUpdated(Task|Note $model, int $projectId): void
    {
        broadcast($model instanceof Task
            ? new TaskUpdated($model, $projectId)
            : new NoteUpdated($model, $projectId));
    }

    /** @return array{0: Task|Note|null, 1: string} model + human label, or [null, errorMessage]. */
    private function resolveSubject(int $projectId, array $args): array
    {
        $hasTask = isset($args['task_id']) && $args['task_id'] !== '';
        $hasNote = isset($args['note_id']) && $args['note_id'] !== '';

        if ($hasTask === $hasNote) {
            return [null, 'Error: provide exactly one of task_id or note_id.'];
        }

        if ($hasTask) {
            $task = Task::where('project_id', $projectId)->whereKey((int) $args['task_id'])->first();
            return $task ? [$task, "task #{$task->id}"] : [null, "Error: task #{$args['task_id']} not found in this project."];
        }

        $note = Note::where('project_id', $projectId)->whereKey((int) $args['note_id'])->first();
        return $note ? [$note, "note #{$note->id}"] : [null, "Error: note #{$args['note_id']} not found in this project."];
    }
}
