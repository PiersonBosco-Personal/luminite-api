<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use App\Models\Project;
use Illuminate\Http\Request;

class McpTokenController extends Controller
{
    public function index(Request $request)
    {
        $tokens = McpToken::where('user_id', $request->user()->id)
            ->with('project:id,name')
            ->get()
            ->map(fn($t) => [
                'id'            => $t->id,
                'name'          => $t->name,
                'project_id'    => $t->project_id,
                'project_name'  => $t->project->name,
                'scopes'        => $t->scopes,
                'last_used_at'  => $t->last_used_at,
                'request_count' => $t->request_count,
                'expires_at'    => $t->expires_at,
                'created_at'    => $t->created_at,
            ]);

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'project_id' => 'required|integer|exists:projects,id',
            'scopes'     => 'sometimes|array',
            'scopes.*'   => 'string|in:read,write',
        ]);

        $project = Project::find($validated['project_id']);

        if (! $project->members()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'You do not have access to this project.'], 403);
        }

        [$token, $rawToken] = McpToken::generate(
            $request->user(),
            $project,
            $validated['name'],
            $validated['scopes'] ?? ['read'],
        );

        return response()->json([
            'data' => [
                'id'         => $token->id,
                'name'       => $token->name,
                'project_id' => $token->project_id,
                'scopes'     => $token->scopes,
                'raw_token'  => $rawToken,
                'created_at' => $token->created_at,
            ],
        ], 201);
    }

    public function destroy(Request $request, McpToken $mcpToken)
    {
        abort_if($mcpToken->user_id !== $request->user()->id, 404);

        $mcpToken->delete();

        return response()->json(['message' => 'Token revoked.']);
    }
}
