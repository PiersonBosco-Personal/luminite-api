<?php

namespace App\Http\Controllers;

use App\Mcp\McpServer;
use App\Mcp\Prompts\InitializeProjectPrompt;
use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\CreateTask;
use App\Mcp\Tools\GetLabels;
use App\Mcp\Tools\GetOpenTasks;
use App\Mcp\Tools\GetProjectContext;
use App\Mcp\Tools\GetProjectNotes;
use App\Mcp\Tools\GetRecentActivity;
use App\Mcp\Tools\GetSections;
use App\Mcp\Tools\GetSessionContext;
use App\Mcp\Tools\InitializeProject;
use App\Mcp\Tools\LogSessionActivity;
use App\Mcp\Tools\SyncTodos;
use Illuminate\Http\Request;

class McpController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all();
        $server  = new McpServer([
            new GetProjectContext(),
            new GetSessionContext(),
            new GetOpenTasks(),
            new GetProjectNotes(),
            new GetRecentActivity(),
            new GetLabels(),
            new GetSections(),
            new CreateTask(),
            new CompleteTask(),
            new SyncTodos(),
            new LogSessionActivity(),
            new InitializeProject(),
        ], [
            new InitializeProjectPrompt(),
        ]);

        $response = $server->handle($payload, $request);

        if (empty($response)) {
            return response()->noContent();
        }

        return response()->json($response);
    }
}
