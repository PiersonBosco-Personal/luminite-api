<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Http\Request;

class GetProjectContext extends Tool
{
    public function definition(): array
    {
        return [
            'name'        => 'get_project_context',
            'description' => 'Returns project details and tech stack for the project scoped to this token.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => new \stdClass(),
                'required'   => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $project = Project::with([
            'techStacks' => fn($q) => $q->whereNull('parent_id')->with('children'),
        ])->findOrFail($this->projectId($request));

        $lines = ["Project: {$project->name}"];

        if ($project->description) {
            $lines[] = "Description: {$project->description}";
        }

        $lines[] = "Status: {$project->status}";

        if ($project->goals) {
            $lines[] = "Goals: {$project->goals}";
        }

        if ($project->architecture_notes) {
            $lines[] = "Architecture Notes: {$project->architecture_notes}";
        }

        if ($project->techStacks->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Tech Stack:';
            foreach ($project->techStacks as $stack) {
                $entry = "- {$stack->name}";
                if ($stack->version) {
                    $entry .= " ({$stack->version})";
                }
                $lines[] = $entry;
                foreach ($stack->children as $child) {
                    $childEntry = "  - {$child->name}";
                    if ($child->version) {
                        $childEntry .= " ({$child->version})";
                    }
                    $lines[] = $childEntry;
                }
            }
        }

        return implode("\n", $lines);
    }
}
