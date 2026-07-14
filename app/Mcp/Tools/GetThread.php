<?php

namespace App\Mcp\Tools;

use App\Models\ThreadEntry;
use Illuminate\Http\Request;

class GetThread extends Tool
{
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 50;

    public function definition(): array
    {
        return [
            'name'        => 'get_thread',
            'description' => 'Read the project memory stream ("the Thread") — recent decisions, dead-ends, gotchas, and where work was left off, newest first. Call this to catch up on the project\'s recent head-space or to check whether something was tried before. Filter by type or task_id. The #id shown is for your tool calls only — refer to items by title when talking to the user.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'limit'   => ['type' => 'integer', 'description' => 'Max entries. Default 15, max 50.'],
                    'type'    => ['type' => 'string', 'enum' => ThreadEntry::TYPES],
                    'task_id' => ['type' => 'integer', 'description' => 'Only entries breadcrumbed to this task.'],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $limit = min((int) ($args['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $query = ThreadEntry::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if (isset($args['type']) && $args['type'] !== '') {
            $query->where('type', (string) $args['type']);
        }
        if (isset($args['task_id']) && $args['task_id'] !== '') {
            $query->where('task_id', (int) $args['task_id']);
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            return 'Project memory is empty — no thread entries yet.';
        }

        $lines = ['Project Memory (newest first):'];
        foreach ($entries as $entry) {
            $lines[] = "- [{$entry->type}] {$entry->content} — {$entry->created_at->diffForHumans()}";
        }

        return implode("\n", $lines);
    }
}
