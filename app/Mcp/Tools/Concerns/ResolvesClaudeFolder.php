<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\NoteFolder;

trait ResolvesClaudeFolder
{
    /**
     * Find or create the project's root-level "Claude" folder — the home for all
     * MCP-authored notes. Returns [NoteFolder, wasCreated]. If several root folders
     * named "Claude" somehow exist, the oldest (lowest id) is used.
     *
     * Must be called within a DB transaction — it locks the project row to
     * serialize concurrent folder resolution.
     */
    protected function resolveClaudeFolder(int $projectId, int $userId): array
    {
        // Serialize concurrent folder resolution for this project (same lockForUpdate
        // pattern as InitializeProject) so two simultaneous create_note calls can't each
        // insert a duplicate root "Claude" folder. Relies on running inside a transaction.
        \App\Models\Project::whereKey($projectId)->lockForUpdate()->first();

        $existing = NoteFolder::where('project_id', $projectId)
            ->whereNull('parent_id')
            ->where('name', 'Claude')
            ->orderBy('id')
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $position = (int) NoteFolder::where('project_id', $projectId)
            ->whereNull('parent_id')
            ->max('position') + 1;

        $folder = NoteFolder::create([
            'project_id' => $projectId,
            'parent_id'  => null,
            'created_by' => $userId,
            'name'       => 'Claude',
            'position'   => $position,
        ]);

        return [$folder, true];
    }
}
