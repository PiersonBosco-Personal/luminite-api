<?php

namespace App\Mcp;

use App\Mcp\Tools\Tool;
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
            Log::error('MCP tool failed', $context + [
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                'exception'   => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('MCP tool completed', $context + [
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
        ]);

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
}
