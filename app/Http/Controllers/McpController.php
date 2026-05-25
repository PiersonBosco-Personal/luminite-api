<?php

namespace App\Http\Controllers;

use App\Mcp\McpServer;
use App\Mcp\Tools\GetProjectContext;
use Illuminate\Http\Request;

class McpController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all();
        $server  = new McpServer([new GetProjectContext()]);

        return response()->json($server->handle($payload, $request));
    }
}
