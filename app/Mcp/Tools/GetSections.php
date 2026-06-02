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
            'description' => 'Returns all task sections (columns) for this project in order with their IDs. Call before creating tasks or when the user references a section by name.',
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
