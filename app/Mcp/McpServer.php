<?php

namespace App\Mcp;

use App\Mcp\Tools\Tool;
use App\Models\McpHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class McpServer
{
    /** @param Tool[] $tools */
    public function __construct(private array $tools) {}

    public function handle(array $payload, Request $request): array
    {
        // Notifications have no id — no response required
        if (!array_key_exists('id', $payload)) {
            return [];
        }

        $method = $payload['method'] ?? '';
        $id     = $payload['id'] ?? null;

        return match ($method) {
            'initialize' => $this->initialize($id),
            'tools/list' => $this->toolsList($id),
            'tools/call' => $this->toolsCall($payload, $request, $id),
            default      => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    private function initialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'protocolVersion' => '2024-11-05',
                'serverInfo'      => ['name' => 'luminite', 'version' => '1.0.0'],
                'capabilities'    => ['tools' => new \stdClass()],
            ],
        ];
    }

    private function toolsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'tools' => array_map(fn(Tool $t) => $t->definition(), $this->tools),
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

            return $this->error($id, -32601, "Tool not found: {$name}");
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

            return $this->error($id, -32603, $e->getMessage());
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
        McpHistory::create([
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
    }

    private function summarize(string $text): ?string
    {
        $line = strtok($text, "\n");
        if ($line === false) {
            return null;
        }
        return mb_substr(trim($line), 0, 160);
    }
}
