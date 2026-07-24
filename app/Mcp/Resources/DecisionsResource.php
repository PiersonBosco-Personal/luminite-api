<?php

namespace App\Mcp\Resources;

use App\Mcp\Tools\GetDecisions;
use Illuminate\Http\Request;

class DecisionsResource extends Resource
{
    public function definition(): array
    {
        return [
            'uri'         => 'luminite://decisions',
            'name'        => 'Active decisions (current truth)',
            'description' => 'The project decision log — active, settled decisions and their rationale (the current truth), newest first.',
            'mimeType'    => 'text/markdown',
        ];
    }

    public function read(Request $request): string
    {
        return (new GetDecisions)->run([], $request);
    }
}
