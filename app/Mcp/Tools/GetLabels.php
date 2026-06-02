<?php

namespace App\Mcp\Tools;

use App\Models\Label;
use Illuminate\Http\Request;

class GetLabels extends Tool
{
    public function definition(): array
    {
        return [
            'name'        => 'get_labels',
            'description' => 'Returns all labels defined for this project with their IDs. Call before filtering tasks by label or before creating tasks that need labels.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => new \stdClass(),
                'required'   => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $labels = Label::where('project_id', $this->projectId($request))
            ->orderBy('name')
            ->get();

        if ($labels->isEmpty()) {
            return 'No labels defined for this project.';
        }

        $lines = ['Labels:'];
        foreach ($labels as $label) {
            $lines[] = "- [{$label->id}] {$label->name} ({$label->color})";
        }

        return implode("\n", $lines);
    }
}
