<?php

namespace App\Http\Controllers;

use App\Mcp\McpServer;
use App\Mcp\Prompts\InitializeProjectPrompt;
use App\Mcp\Prompts\WrapUpPrompt;
use App\Mcp\Tools\AddThreadEntry;
use App\Mcp\Tools\CompleteTask;
use App\Mcp\Tools\CreateNote;
use App\Mcp\Tools\CreateTask;
use App\Mcp\Tools\GetDecisions;
use App\Mcp\Tools\GetLabels;
use App\Mcp\Tools\GetOpenTasks;
use App\Mcp\Tools\GetProjectNotes;
use App\Mcp\Tools\GetRecentActivity;
use App\Mcp\Tools\GetSections;
use App\Mcp\Tools\GetSessionContext;
use App\Mcp\Tools\GetThread;
use App\Mcp\Tools\InitializeProject;
use App\Mcp\Tools\LogDecision;
use App\Mcp\Tools\LogSessionActivity;
use App\Mcp\Tools\ManageLabel;
use App\Mcp\Tools\ManageSection;
use App\Mcp\Tools\Recall;
use App\Mcp\Tools\UpdateNote;
use App\Mcp\Tools\UpdateTask;
use Illuminate\Http\Request;

class McpController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->json()->all();
        $server = new McpServer([
            new AddThreadEntry,
            new LogDecision,
            new GetSessionContext,
            new GetOpenTasks,
            new GetProjectNotes,
            new GetRecentActivity,
            new GetThread,
            new GetDecisions,
            new Recall,
            new GetLabels,
            new GetSections,
            new ManageSection,
            new ManageLabel,
            new CreateNote,
            new UpdateNote,
            new CreateTask,
            new UpdateTask,
            new CompleteTask,
            new LogSessionActivity,
            new InitializeProject,
        ], [
            new InitializeProjectPrompt,
            new WrapUpPrompt,
        ]);

        $response = $server->handle($payload, $request);

        if (empty($response)) {
            return response()->noContent();
        }

        return response()->json($response);
    }
}
