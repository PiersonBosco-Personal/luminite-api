<?php

namespace App\Mcp\Tools;

use Illuminate\Http\Request;

class LogSessionActivity extends Tool
{
    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'log_session_activity',
            'description' => 'Record a structured summary of a coding session (what changed, which tasks were completed). Stored as MCP telemetry for later analysis; does not post to the project activity feed. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'summary'         => ['type' => 'string'],
                    'files_changed'   => ['type' => ['array', 'integer'], 'items' => ['type' => 'string']],
                    'tasks_completed' => ['type' => 'array', 'items' => ['type' => 'integer']],
                ],
                'required' => ['summary'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $summary = trim((string) ($args['summary'] ?? ''));
        if ($summary === '') {
            return 'Error: summary is required.';
        }

        $files = $args['files_changed'] ?? null;
        $tasks = $args['tasks_completed'] ?? null;

        $fileCount = is_array($files) ? count($files) : (is_numeric($files) ? (int) $files : 0);
        $taskCount = is_array($tasks) ? count($tasks) : 0;

        // No DB mutation: the structured payload is captured by McpServer's
        // central mcp_history row (arguments + this summary line).
        return "Logged session: {$summary} ({$fileCount} files, {$taskCount} tasks)";
    }
}
