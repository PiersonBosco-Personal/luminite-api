<?php

namespace App\Mcp;

use App\Mcp\Tools\Tool;
use Illuminate\Http\Request;

class McpServer
{
    /** @param Tool[] $tools */
    public function __construct(private array $tools) {}

    public function handle(array $payload, Request $request): array
    {
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
            return $this->error($id, -32601, "Tool not found: {$name}");
        }

        $text = $tool->run($args, $request);

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
