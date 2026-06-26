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
            'description' => 'List all labels (id, name, color). Call this to find a label id before attaching it, or to check whether a label already exists. The #id/[id] shown is for your tool calls only — never repeat it to the user; refer to items by title.',
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
