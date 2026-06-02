<?php

namespace App\Http\Controllers;

use App\Mcp\McpServer;
use App\Mcp\Tools\GetProjectContext;
use App\Mcp\Tools\GetLabels;
use Illuminate\Http\Request;

class McpController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all();
        $server  = new McpServer([
            new GetProjectContext(),
            new GetLabels(),
        ]);

        $response = $server->handle($payload, $request);

        if (empty($response)) {
            return response()->noContent();
        }

        return response()->json($response);
    }
}
