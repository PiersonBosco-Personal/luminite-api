<?php

namespace App\Mcp\Tools;

use Illuminate\Http\Request;

abstract class Tool
{
    abstract public function definition(): array;
    abstract public function run(array $args, Request $request): string;

    protected function userId(Request $request): int
    {
        return $request->attributes->get('mcp_user_id');
    }

    protected function projectId(Request $request): int
    {
        return $request->attributes->get('mcp_project_id');
    }
}
