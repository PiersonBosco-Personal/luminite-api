<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Http\Request;

class GetProjectNotes extends Tool
{
    public function definition(): array
    {
        return [
            'name'        => 'get_project_notes',
            'description' => 'Search project notes. Call this before writing a new note to avoid duplicating an existing decision, or to pull prior context on a feature. Filter by keyword or tag.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'keyword' => ['type' => 'string'],
                    'tag'     => ['type' => 'string', 'description' => 'Label name to filter by'],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $query = Note::with(['folder', 'labels'])
            ->where('project_id', $this->projectId($request));

        if (isset($args['keyword'])) {
            $keyword = $args['keyword'];
            $query->where(fn ($q) =>
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('content', 'like', "%{$keyword}%")
            );
        }

        if (isset($args['tag'])) {
            $query->whereHas('labels', fn ($q) =>
                $q->where('name', 'like', "%{$args['tag']}%")
            );
        }

        $notes = $query->orderBy('is_pinned', 'desc')->orderBy('position')->get();

        if ($notes->isEmpty()) {
            return 'No notes match the given filters.';
        }

        $lines = ["Notes ({$notes->count()}):"];
        foreach ($notes as $note) {
            $pinned  = $note->is_pinned ? ' [pinned]' : '';
            $folder  = $note->folder ? " — folder: {$note->folder->name}" : '';
            $preview = $this->extractText($note->content);
            if (mb_strlen($preview) > 300) {
                $preview = mb_substr($preview, 0, 300) . '...';
            }

            $lines[] = "- {$note->title}{$pinned}{$folder}";
            if ($preview !== '') {
                $lines[] = "  {$preview}";
            }
        }

        return implode("\n", $lines);
    }

    private function extractText(mixed $content): string
    {
        if ($content === null) {
            return '';
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $content;
            }
            $content = $decoded;
        }

        if (! is_array($content)) {
            return '';
        }

        $text = '';

        if (isset($content['text']) && is_string($content['text'])) {
            $text .= $content['text'];
        }

        if (isset($content['content']) && is_array($content['content'])) {
            foreach ($content['content'] as $node) {
                $nodeText = $this->extractText($node);
                if ($nodeText !== '') {
                    $text .= $nodeText . ' ';
                }
            }
        }

        return trim($text);
    }
}
