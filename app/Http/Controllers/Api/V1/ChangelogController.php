<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskCompletionResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TaskCompletion;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    /** On a first-ever view (null anchor), show completions by others from this many days back. */
    private const FIRST_VIEW_WINDOW_DAYS = 14;

    /** Latest-canonical completion per task, newest first (both members). */
    public function index(Request $request, Project $project)
    {
        $completions = $this->latestPerTask($project)
            ->with('task', 'completedBy')
            ->orderByDesc('created_at')
            ->paginate(20);

        return TaskCompletionResource::collection($completions);
    }

    /**
     * Completions by OTHER members the caller hasn't seen. On a first-ever view
     * (null anchor) we show a recent window instead of stamping now() — otherwise
     * a teammate's first visit would always read "all caught up" and they'd never
     * see changes made before they arrived. The anchor advances ONLY via
     * POST /viewed, so the banner can't clear before the user opens it.
     */
    public function digest(Request $request, Project $project)
    {
        $member = $this->member($project, $request->user()->id);

        $since = $member->last_viewed_changelog_at
            ?? now()->subDays(self::FIRST_VIEW_WINDOW_DAYS);

        $completions = $this->latestPerTask($project)
            ->where('completed_by_user_id', '!=', $request->user()->id)
            ->where('created_at', '>', $since)
            ->with('task', 'completedBy')
            ->orderByDesc('created_at')
            ->get();

        return TaskCompletionResource::collection($completions)
            ->additional(['meta' => ['unread_count' => $completions->count()]]);
    }

    /** Advance the caller's anchor to now (called only on a real view). */
    public function viewed(Request $request, Project $project)
    {
        $this->member($project, $request->user()->id)
            ->update(['last_viewed_changelog_at' => now()]);

        return response()->json(['message' => 'Changelog marked viewed.', 'errors' => (object) []]);
    }

    /** Subquery: ids of the most-recent completion row per task in this project. */
    private function latestPerTask(Project $project)
    {
        $latestIds = TaskCompletion::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('task_id', $project->tasks()->select('id'))
            ->groupBy('task_id');

        return TaskCompletion::query()->whereIn('id', $latestIds);
    }

    private function member(Project $project, int $userId): ProjectMember
    {
        return ProjectMember::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $userId],
            ['role' => 'member'],
        );
    }
}
