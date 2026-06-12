<?php

namespace App\Mcp\Tools;

use App\Events\NoteUpdated;
use App\Mcp\Tools\Concerns\BuildsNoteContent;
use App\Models\Note;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class UpdateNote extends Tool
{
    use BuildsNoteContent;

    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'update_note',
            'description' => 'Edit a note by id. Use append to add text to the end of the note (most common while working — e.g. logging progress). Use content to replace the whole body. Use title to rename. Provide at least one of append, content, or title. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'note_id' => ['type' => 'integer'],
                    'title'   => ['type' => 'string'],
                    'append'  => ['type' => 'string', 'description' => 'Plain text appended as new paragraphs.'],
                    'content' => ['type' => 'string', 'description' => 'Plain text that replaces the whole body.'],
                ],
                'required' => ['note_id'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);
        $noteId    = (int) ($args['note_id'] ?? 0);

        $note = Note::where('project_id', $projectId)->whereKey($noteId)->first();
        if (! $note) {
            return "Error: note #{$noteId} not found in this project.";
        }

        $updates = [];

        if (array_key_exists('title', $args) && trim((string) $args['title']) !== '') {
            $updates['title'] = trim((string) $args['title']);
        }
        if (array_key_exists('content', $args)) {
            $updates['content'] = $this->textToTiptap((string) $args['content']);
        } elseif (array_key_exists('append', $args) && (string) $args['append'] !== '') {
            $updates['content'] = $this->appendToTiptap($note->content, (string) $args['append']);
        }

        if (! $updates) {
            return "No changes: provide title, content, or append for note #{$note->id}.";
        }

        $note->update($updates);

        broadcast(new NoteUpdated($note, $projectId));

        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    'note.updated',
            subjectType:  'note',
            subjectLabel: $note->title,
            subjectId:    $note->id,
            description:  "updated note {$note->title}",
            viaMcp:       true,
        );

        return "Updated note #{$note->id}: {$note->title}";
    }
}
