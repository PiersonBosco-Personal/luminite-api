<?php

namespace App\Mcp\Tools;

use App\Events\NoteCreated;
use App\Mcp\Tools\Concerns\BuildsNoteContent;
use App\Mcp\Tools\Concerns\ResolvesTaskInput;
use App\Models\Note;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateNote extends Tool
{
    use BuildsNoteContent;
    use ResolvesTaskInput;

    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'create_note',
            'description' => 'Create a project note. Call this to record a decision, a bug investigation, or a handoff for a teammate. Pass task_id to attach the note to a task (this is how you leave a comment/handoff on a task). content is plain text/markdown. labels accept ids or names. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'title'   => ['type' => 'string'],
                    'content' => ['type' => 'string', 'description' => 'Plain text / markdown body.'],
                    'task_id' => ['type' => 'integer', 'description' => 'Optional task to link this note to.'],
                    'labels'  => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
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

        $taskId = null;
        if (isset($args['task_id']) && $args['task_id'] !== '') {
            $taskId = (int) $args['task_id'];
            if (! Task::where('project_id', $projectId)->whereKey($taskId)->exists()) {
                return "Error: task #{$taskId} not found in this project.";
            }
        }

        try {
            $labelIds = $this->resolveLabelIds($projectId, (array) ($args['labels'] ?? []));
        } catch (ToolInputException $e) {
            return 'Error: ' . $e->getMessage();
        }

        $content = $this->textToTiptap((string) ($args['content'] ?? ''));

        $note = DB::transaction(function () use ($projectId, $userId, $taskId, $title, $content, $labelIds) {
            $position = (int) Note::where('project_id', $projectId)->max('position') + 1;

            $note = Note::create([
                'project_id' => $projectId,
                'task_id'    => $taskId,
                'created_by' => $userId,
                'title'      => $title,
                'content'    => $content,
                'is_pinned'  => false,
                'position'   => $position,
            ]);

            if ($labelIds) {
                $note->labels()->sync($labelIds);
            }

            return $note;
        });

        broadcast(new NoteCreated($note, $projectId));

        $linked = $taskId ? " (linked to task #{$taskId})" : '';
        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    'note.created',
            subjectType:  'note',
            subjectLabel: $note->title,
            subjectId:    $note->id,
            description:  "created note {$note->title}{$linked}",
            viaMcp:       true,
        );

        return "Created note #{$note->id}: {$note->title}{$linked}";
    }
}
