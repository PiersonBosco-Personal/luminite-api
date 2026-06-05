<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\McpHistoryResource;
use App\Models\McpHistory;
use App\Models\McpToken;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpProjectController extends Controller
{
    public function stats(Project $project): JsonResponse
    {
        $tokens = McpToken::where('project_id', $project->id)->get();

        $activeTokens = $tokens->filter(fn ($t) => ! $t->isExpired())->count();
        $totalTokens  = $tokens->count();
        $lastUsedAt   = $tokens->max('last_used_at');

        return response()->json(['data' => [
            'requests_this_week'      => 0,
            'requests_last_week'      => 0,
            'active_tokens'           => $activeTokens,
            'total_tokens'            => $totalTokens,
            'last_activity_at'        => $lastUsedAt,
            'last_activity_user'      => null,
            'tasks_completed_this_week' => 0,
        ]]);
    }

    public function activity(Request $request, Project $project): JsonResponse
    {
        $query = McpHistory::where('project_id', $project->id)
            ->with('user')
            ->orderByDesc('created_at');

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $rows = $query->limit(100)->get();

        return response()->json(['data' => McpHistoryResource::collection($rows)]);
    }
}
