<?php

namespace App\Mcp\Tools\Concerns;

trait BuildsNoteContent
{
    /** Convert plain text into a minimal Tiptap doc (one paragraph per line). */
    protected function textToTiptap(string $text): string
    {
        return json_encode($this->wrapParagraphs($text), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Append plain text (as new paragraphs) to an existing Tiptap-JSON doc. */
    protected function appendToTiptap(?string $existingJson, string $text): string
    {
        $doc = $existingJson ? json_decode($existingJson, true) : null;

        if (! is_array($doc) || ($doc['type'] ?? null) !== 'doc' || ! isset($doc['content']) || ! is_array($doc['content'])) {
            return $this->textToTiptap($text);
        }

        $doc['content'] = array_merge($doc['content'], $this->wrapParagraphs($text)['content']);

        return json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array{type:string,content:array<int,mixed>} */
    private function wrapParagraphs(string $text): array
    {
        $lines   = preg_split('/\r\n|\r|\n/', $text) ?: [''];
        $content = [];

        foreach ($lines as $line) {
            $paragraph = ['type' => 'paragraph'];
            if ($line !== '') {
                $paragraph['content'] = [['type' => 'text', 'text' => $line]];
            }
            $content[] = $paragraph;
        }

        return ['type' => 'doc', 'content' => $content];
    }
}
