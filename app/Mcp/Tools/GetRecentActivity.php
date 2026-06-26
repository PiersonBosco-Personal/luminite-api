<?php

namespace App\Mcp\Tools;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GetRecentActivity extends Tool
{
    public function definition(): array
    {
        return [
            'name'        => 'get_recent_activity',
            'description' => 'List recent project activity. Call this to catch up on what changed since you last worked, or to confirm a teammate has not already done something. Filter by since (ISO 8601) and limit. The #id/[id] shown is for your tool calls only — never repeat it to the user; refer to items by title.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'since' => [
                        'type'        => 'string',
                        'description' => 'ISO 8601 date string. Defaults to 48 hours ago.',
                    ],
                    'limit' => [
                        'type'        => 'integer',
                        'description' => 'Max entries to return. Default 20, max 50.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $since = isset($args['since'])
            ? Carbon::parse($args['since'])
            : Carbon::now()->subHours(48);

        $limit = min((int) ($args['limit'] ?? 20), 50);

        $entries = ActivityLog::where('project_id', $this->projectId($request))
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            return "No activity since {$since->toDateTimeString()}.";
        }

        $lines = ["Recent Activity (since {$since->toDateTimeString()}):"];
        foreach ($entries as $entry) {
            $viaMcp = $entry->via_mcp ? ' [via MCP]' : '';
            $lines[] = "{$entry->created_at->toDateTimeString()} — {$entry->description}{$viaMcp}";
        }

        return implode("\n", $lines);
    }
}
