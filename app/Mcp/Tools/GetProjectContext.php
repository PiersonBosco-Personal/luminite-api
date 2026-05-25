<?php

namespace App\Mcp\Tools;

use Illuminate\Http\Request;

class GetProjectContext extends Tool
{
    public function definition(): array
    {
        return [
            'name'        => 'get_project_context',
            'description' => 'Returns project details and tech stack for the project scoped to this token.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => new \stdClass(),
                'required'   => [],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        return '';
    }
}
