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
            'description' => 'Returns recent project activity in chronological order. Defaults to the last 48 hours. Use when the user asks what changed recently, what was completed, or what the team has been doing.',
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
