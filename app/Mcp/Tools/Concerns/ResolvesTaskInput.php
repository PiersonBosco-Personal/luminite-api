<?php

namespace App\Mcp\Tools\Concerns;

use App\Mcp\Tools\ToolInputException;
use App\Models\Label;
use App\Models\TaskSection;

trait ResolvesTaskInput
{
    /** Resolve a section id or name (or null) to a concrete section id within the project. */
    protected function resolveSectionId(int $projectId, mixed $section): int
    {
        if ($section === null || $section === '') {
            return $this->defaultSectionId($projectId);
        }

        if (is_numeric($section)) {
            $id = (int) $section;
            $exists = TaskSection::where('project_id', $projectId)->whereKey($id)->exists();
            if (! $exists) {
                throw new ToolInputException("Section #{$id} not found in this project.");
            }
            return $id;
        }

        $found = TaskSection::where('project_id', $projectId)
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $section)])
            ->first();

        if (! $found) {
            throw new ToolInputException("Section '{$section}' not found in this project.");
        }

        return $found->id;
    }

    /** Lowest-position section in the project. */
    protected function defaultSectionId(int $projectId): int
    {
        $section = TaskSection::where('project_id', $projectId)->orderBy('position')->first();

        if (! $section) {
            throw new ToolInputException('This project has no sections to place the task in.');
        }

        return $section->id;
    }

    /**
     * Resolve label ids or names to label ids. Unknown names are auto-created
     * with a neutral default color. Returns a de-duplicated array of ids.
     */
    protected function resolveLabelIds(int $projectId, array $labels): array
    {
        $ids = [];

        foreach ($labels as $label) {
            if (is_numeric($label)) {
                $found = Label::where('project_id', $projectId)->whereKey((int) $label)->first();
                if ($found) {
                    $ids[] = $found->id;
                }
                continue;
            }

            $name = trim((string) $label);
            if ($name === '') {
                continue;
            }

            $found = Label::where('project_id', $projectId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first()
                ?? Label::create(['project_id' => $projectId, 'name' => $name, 'color' => '#94a3b8']);

            $ids[] = $found->id;
        }

        return array_values(array_unique($ids));
    }
}
