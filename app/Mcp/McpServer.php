<?php

namespace App\Mcp;

use App\Events\McpActivityCreated;
use App\Mcp\Prompts\Prompt;
use App\Mcp\Tools\Tool;
use App\Models\McpHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class McpServer
{
    private const DEFAULT_PROTOCOL_VERSION = '2025-06-18';

    /**
     * @param Tool[]   $tools
     * @param Prompt[] $prompts
     */
    public function __construct(private array $tools, private array $prompts = []) {}

    public function handle(array $payload, Request $request): array
    {
        // Notifications have no id — no response required
        if (!array_key_exists('id', $payload)) {
            return [];
        }

        $method = $payload['method'] ?? '';
        $id     = $payload['id'] ?? null;

        return match ($method) {
            'initialize'   => $this->initialize($payload, $id),
            'tools/list'   => $this->toolsList($id),
            'tools/call'   => $this->toolsCall($payload, $request, $id),
            'prompts/list' => $this->promptsList($id),
            'prompts/get'  => $this->promptsGet($payload, $id),
            default        => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    private function initialize(array $payload, mixed $id): array
    {
        $requested = $payload['params']['protocolVersion'] ?? null;
        $version   = is_string($requested) && $requested !== ''
            ? $requested
            : self::DEFAULT_PROTOCOL_VERSION;

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'protocolVersion' => $version,
                'serverInfo'      => ['name' => 'luminite', 'version' => '1.0.0'],
                'capabilities'    => ['tools' => new \stdClass(), 'prompts' => new \stdClass()],
                'instructions'    => $this->instructions(),
            ],
        ];
    }

    /**
     * Zero-touch second channel for the keep-in-sync workflow. Ships with the
     * server so it stays current even if a repo's CLAUDE.md block drifts. Lower
     * priority than CLAUDE.md — belt-and-suspenders, not the primary lever.
     */
    private function instructions(): string
    {
        return implode("\n", [
            'This project is tracked in Luminite. Keep it in sync as you work, without being asked:',
            '- Starting a task → update_task to move it to In Progress.',
            '- Finishing a task → complete_task.',
            '- Notable decision (architecture, tradeoff, scope change) → create_note linked with task_id.',
            'If you are missing project state, call get_session_context first.',
        ]);
    }

    private function toolsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'tools' => array_map(
                    fn (Tool $t) => $t->definition() + ['annotations' => $t->annotations()],
                    $this->tools
                ),
            ],
        ];
    }

    private function promptsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'prompts' => array_map(fn (Prompt $p) => $p->definition(), $this->prompts),
            ],
        ];
    }

    private function promptsGet(array $payload, mixed $id): array
    {
        $name = $payload['params']['name'] ?? '';

        $prompt = collect($this->prompts)->first(fn (Prompt $p) => $p->definition()['name'] === $name);

        if (! $prompt) {
            return $this->error($id, -32602, "Prompt not found: {$name}");
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'description' => $prompt->definition()['description'] ?? '',
                'messages'    => $prompt->messages(),
            ],
        ];
    }

    private function toolsCall(array $payload, Request $request, mixed $id): array
    {
        $name = $payload['params']['name'] ?? '';
        $args = $payload['params']['arguments'] ?? [];

        $tool = collect($this->tools)->first(fn(Tool $t) => $t->definition()['name'] === $name);

        if (! $tool) {
            Log::warning('MCP tool not found', [
                'tool'       => $name,
                'user_id'    => $request->attributes->get('mcp_user_id'),
                'project_id' => $request->attributes->get('mcp_project_id'),
            ]);

            $this->recordHistory($request, $name, $args, 'error', null, null, "tool not found: {$name}");

            return $this->error($id, -32601, "Tool not found: {$name}");
        }

        $scopes = $request->attributes->get('mcp_scopes', []);

        if (! in_array($tool->requiredScope(), $scopes, true)) {
            $message = "This token lacks the '{$tool->requiredScope()}' scope";
            $this->recordHistory($request, $name, $args, 'error', null, null, "denied: {$message}");

            return $this->error($id, -32603, $message);
        }

        $context = [
            'tool'       => $name,
            'user_id'    => $request->attributes->get('mcp_user_id'),
            'project_id' => $request->attributes->get('mcp_project_id'),
            'args'       => $args,
        ];

        Log::info('MCP tool called', $context);

        $start = microtime(true);

        try {
            $text = $tool->run($args, $request);
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);

            Log::error('MCP tool failed', $context + [
                'duration_ms' => $duration,
                'exception'   => $e->getMessage(),
            ]);

            $this->recordHistory($request, $name, $args, 'error', $duration, null, $e->getMessage());

            return $this->error($id, -32603, 'Internal error');
        }

        $duration = (int) round((microtime(true) - $start) * 1000);

        Log::info('MCP tool completed', $context + ['duration_ms' => $duration]);

        $this->recordHistory($request, $name, $args, 'success', $duration, $this->summarize($text), null);

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'content' => [['type' => 'text', 'text' => $text]],
            ],
        ];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ];
    }

    private function recordHistory(
        Request $request,
        string  $tool,
        array   $args,
        string  $status,
        ?int    $durationMs,
        ?string $summary,
        ?string $error,
    ): void {
        $history = McpHistory::create([
            'mcp_token_id'   => $request->attributes->get('mcp_token_id'),
            'user_id'        => $request->attributes->get('mcp_user_id'),
            'project_id'     => $request->attributes->get('mcp_project_id'),
            'tool'           => $tool,
            'arguments'      => $args ?: null,
            'status'         => $status,
            'duration_ms'    => $durationMs,
            'result_summary' => $summary,
            'error_message'  => $error,
        ]);

        // Push the new entry to any open Claude MCP pages in real time. The trigger
        // is Claude Code (not a browser socket), so broadcast to everyone — no toOthers().
        if ($history->project_id) {
            broadcast(new McpActivityCreated($history, (int) $history->project_id));
        }
    }

    private function summarize(string $text): ?string
    {
        $line = explode("\n", $text, 2)[0];
        $line = trim($line);

        return $line === '' ? null : mb_substr($line, 0, 160);
    }
}
