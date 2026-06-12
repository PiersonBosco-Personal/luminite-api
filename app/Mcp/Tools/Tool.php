<?php

namespace App\Mcp\Tools;

use Illuminate\Http\Request;

abstract class Tool
{
    abstract public function definition(): array;
    abstract public function run(array $args, Request $request): string;

    public function requiredScope(): string
    {
        return 'read';
    }

    /**
     * MCP tool annotations. Read tools are read-only; write tools are not.
     * No v1 tool deletes data, so destructiveHint is false everywhere.
     * Individual tools may override.
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint'    => $this->requiredScope() === 'read',
            'destructiveHint' => false,
        ];
    }

    protected function userId(Request $request): int
    {
        return $request->attributes->get('mcp_user_id');
    }

    protected function projectId(Request $request): int
    {
        return $request->attributes->get('mcp_project_id');
    }
}
