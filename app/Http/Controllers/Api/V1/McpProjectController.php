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
        $tokens       = McpToken::where('project_id', $project->id)->get();
        $activeTokens = $tokens->filter(fn ($t) => ! $t->isExpired())->count();

        $now       = now();
        $weekStart = $now->copy()->subDays(7);
        $prevStart = $now->copy()->subDays(14);

        $base = McpHistory::where('project_id', $project->id);

        $thisWeek = (clone $base)->where('created_at', '>=', $weekStart)->count();
        $lastWeek = (clone $base)->whereBetween('created_at', [$prevStart, $weekStart])->count();
        $errors   = (clone $base)->where('created_at', '>=', $weekStart)->where('status', 'error')->count();
        $avgMs    = (clone $base)->where('created_at', '>=', $weekStart)->avg('duration_ms');

        $tasksCompleted = (clone $base)
            ->where('created_at', '>=', $weekStart)
            ->where('tool', 'complete_task')
            ->where('status', 'success')
            ->count();

        $latest = (clone $base)->with('user')->orderByDesc('created_at')->first();

        return response()->json(['data' => [
            'requests_this_week'        => $thisWeek,
            'requests_last_week'        => $lastWeek,
            'active_tokens'             => $activeTokens,
            'total_tokens'              => $tokens->count(),
            'last_activity_at'          => $latest?->created_at,
            'last_activity_user'        => $latest?->user?->name,
            'tasks_completed_this_week' => $tasksCompleted,
            'error_rate'                => $thisWeek > 0 ? round($errors / $thisWeek, 3) : 0,
            'avg_duration_ms'           => $avgMs !== null ? (int) round($avgMs) : null,
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
