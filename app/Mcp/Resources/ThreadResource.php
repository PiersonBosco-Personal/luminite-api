<?php

namespace App\Mcp\Resources;

use App\Mcp\Tools\GetThread;
use Illuminate\Http\Request;

class ThreadResource extends Resource
{
    public function definition(): array
    {
        return [
            'uri'         => 'luminite://thread',
            'name'        => 'Project Memory — recent stream',
            'description' => 'The recent project memory stream ("the Thread"): decisions, dead-ends, gotchas, and where work was left off, newest first.',
            'mimeType'    => 'text/markdown',
        ];
    }

    public function read(Request $request): string
    {
        // Delegate to the read tool so formatting/empty-state/project-scope stay
        // in one place — the resource is an @-door onto get_thread's output.
        return (new GetThread)->run([], $request);
    }
}
