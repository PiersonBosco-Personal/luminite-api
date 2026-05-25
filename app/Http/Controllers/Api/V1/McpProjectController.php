<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

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

    public function activity(Project $project): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
