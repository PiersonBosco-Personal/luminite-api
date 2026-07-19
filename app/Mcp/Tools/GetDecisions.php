<?php

namespace App\Mcp\Tools;

use App\Models\Decision;
use Illuminate\Http\Request;

class GetDecisions extends Tool
{
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 50;

    public function definition(): array
    {
        return [
            'name'        => 'get_decisions',
            'description' => 'Read the project\'s decision log — the durable, settled choices and their rationale. By default returns only ACTIVE decisions (the current truth), newest first. Pass include_superseded=true to see the full history, including what each superseded decision was replaced by. Use this to answer "what did we decide about X?" or "why did we switch off Y?". The #id shown is for your tool calls only — refer to decisions by their text when talking to the user.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'include_superseded' => ['type' => 'boolean', 'description' => 'Include superseded decisions (the history). Default false — active only.'],
                    'limit'              => ['type' => 'integer', 'description' => 'Max decisions. Default 15, max 50.'],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        // max(1, ...) guards a negative limit, which would otherwise drop the
        // LIMIT clause and bypass the cap (same guard as get_thread).
        $limit = max(1, min((int) ($args['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));

        $raw = $args['include_superseded'] ?? false;
        $includeSuperseded = $raw === true || $raw === 'true' || $raw === 1 || $raw === '1';

        $query = Decision::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if (! $includeSuperseded) {
            $query->where('status', 'active');
        }

        $decisions = $query->get();

        if ($decisions->isEmpty()) {
            return $includeSuperseded
                ? 'No decisions logged yet.'
                : 'No active decisions. (Pass include_superseded=true to see history, if any.)';
        }

        $lines = [$includeSuperseded ? 'Decision Log (newest first):' : 'Active Decisions (newest first):'];
        foreach ($decisions as $d) {
            $tag = $d->status === 'superseded'
                ? ' [superseded' . ($d->superseded_by_id ? " by #{$d->superseded_by_id}" : '') . ']'
                : '';
            $lines[] = "- #{$d->id}{$tag} {$d->decision} — {$d->rationale} ({$d->created_at->diffForHumans()})";
        }

        return implode("\n", $lines);
    }
}
