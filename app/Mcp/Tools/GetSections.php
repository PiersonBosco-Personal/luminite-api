<?php

namespace App\Mcp\Tools;

use App\Models\TaskSection;
use Illuminate\Http\Request;

class GetSections extends Tool
{
    public function definition(): array
    {
        return [
            'name'        => 'get_sections',
            'description' => 'List all task sections (id, name) in board order. Call this to find a section id before creating or moving a task.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => new \stdClass(),
                'required'   => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $sections = TaskSection::where('project_id', $this->projectId($request))
            ->orderBy('position')
            ->get();

        if ($sections->isEmpty()) {
            return 'No sections defined for this project.';
        }

        $lines = ['Sections:'];
        foreach ($sections as $section) {
            $lines[] = "- [{$section->id}] {$section->name}";
        }

        return implode("\n", $lines);
    }
}
