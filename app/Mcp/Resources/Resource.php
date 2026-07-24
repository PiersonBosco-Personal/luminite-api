<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;

abstract class Resource
{
    /** MCP resource descriptor: ['uri', 'name', 'description', 'mimeType']. */
    abstract public function definition(): array;

    /** The resource contents as text, project-scoped via the request. */
    abstract public function read(Request $request): string;
}
