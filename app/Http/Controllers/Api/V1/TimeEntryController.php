<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\TimeEntryLogged;
use App\Events\TimerStarted;
use App\Events\TimerStopped;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartTimerRequest;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\WorkType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeEntryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Project $project)
    {
        $query = $project->timeEntries()->with(['user', 'workType', 'task']);

        if ($request->filled('task_id')) {
            $query->where('task_id', $request->integer('task_id'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('work_type_id')) {
            $query->where('work_type_id', $request->integer('work_type_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('logged_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('logged_at', '<=', $request->date('to'));
        }

        $entries = $query->orderByDesc('logged_at')->orderByDesc('id')->get();

        return TimeEntryResource::collection($entries);
    }

    public function store(StoreTimeEntryRequest $request, Project $project)
    {
        $entry = $project->timeEntries()->create(array_merge(
            $request->validated(),
            [
                'user_id'   => $request->user()->id,
                'logged_at' => $request->date('logged_at') ?? today(),
            ]
        ));

        $entry->load('user', 'workType', 'task');

        broadcast(new TimeEntryLogged($entry, $project->id))->toOthers();

        return (new TimeEntryResource($entry))->response()->setStatusCode(201);
    }

    public function update(UpdateTimeEntryRequest $request, Project $project, TimeEntry $timeEntry)
    {
        abort_if($timeEntry->project_id !== $project->id, 404);
        $this->authorize('update', $timeEntry);

        $timeEntry->update($request->validated());
        $timeEntry->load('user', 'workType', 'task');

        broadcast(new TimeEntryLogged($timeEntry, $project->id))->toOthers();

        return new TimeEntryResource($timeEntry);
    }

    public function activeTimer(Request $request)
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->active()
            ->with(['user', 'workType', 'task'])
            ->first();

        return response()->json([
            'data' => $entry ? (new TimeEntryResource($entry))->resolve() : null,
        ]);
    }

    public function startTimer(StartTimerRequest $request, Project $project)
    {
        $userId = $request->user()->id;

        $hasActive = TimeEntry::query()
            ->where('user_id', $userId)
            ->active()
            ->exists();

        if ($hasActive) {
            return response()->json([
                'message' => 'You already have an active timer running.',
            ], 409);
        }

        $entry = $project->timeEntries()->create([
            'task_id'      => $request->integer('task_id'),
            'work_type_id' => $request->input('work_type_id'),
            'user_id'      => $userId,
            'description'  => $request->input('description'),
            'started_at'   => now(),
            'logged_at'    => today(),
        ]);

        $entry->load('user', 'workType', 'task');

        broadcast(new TimerStarted($entry, $project->id))->toOthers();

        return (new TimeEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function stopTimer(Request $request, Project $project)
    {
        $entry = $project->timeEntries()
            ->where('user_id', $request->user()->id)
            ->active()
            ->first();

        abort_if(! $entry, 404, 'No active timer for this user in this project.');

        $now     = now();
        $minutes = max(1, (int) round($entry->started_at->diffInSeconds($now) / 60));

        $entry->update([
            'duration_minutes' => $minutes,
            'stopped_at'       => $now,
        ]);

        $entry->load('user', 'workType', 'task');

        broadcast(new TimerStopped($entry, $project->id))->toOthers();
        broadcast(new TimeEntryLogged($entry, $project->id))->toOthers();

        return new TimeEntryResource($entry);
    }

    public function report(Request $request, Project $project)
    {
        $groupBy = $request->input('group_by', 'work_type');
        abort_unless(in_array($groupBy, ['user', 'work_type', 'task'], true), 422, 'Invalid group_by.');

        $query = $project->timeEntries()->whereNotNull('duration_minutes');

        if ($request->filled('from')) {
            $query->whereDate('logged_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('logged_at', '<=', $request->date('to'));
        }

        $entries = $query->with(['user', 'workType', 'task'])->get();

        $total   = (int) $entries->sum('duration_minutes');
        $buckets = $entries->groupBy(function (TimeEntry $e) use ($groupBy) {
            return match ($groupBy) {
                'user'      => $e->user_id,
                'work_type' => $e->work_type_id,
                'task'      => $e->task_id,
            };
        });

        $groups = $buckets->map(function ($bucket, $key) use ($groupBy, $total) {
            $minutes = (int) $bucket->sum('duration_minutes');
            $first   = $bucket->first();
            $label   = match ($groupBy) {
                'user'      => $first->user?->name ?? 'Unknown user',
                'work_type' => $first->workType?->name ?? 'No work type',
                'task'      => $first->task?->title ?? 'Deleted task',
            };

            return [
                'id'      => is_numeric($key) ? (int) $key : ($key === '' ? null : $key),
                'label'   => $label,
                'color'   => $groupBy === 'work_type' ? $first->workType?->color : null,
                'minutes' => $minutes,
                'percent' => $total > 0 ? round($minutes / $total * 100, 2) : 0.0,
            ];
        })->values()->all();

        // Attach per-user work-type breakdown when grouping by user; null otherwise.
        if ($groupBy === 'user') {
            $breakdownQuery = DB::table('time_entries')
                ->where('project_id', $project->id)
                ->whereNotNull('duration_minutes');

            if ($request->filled('from')) {
                $breakdownQuery->whereDate('logged_at', '>=', $request->date('from'));
            }
            if ($request->filled('to')) {
                $breakdownQuery->whereDate('logged_at', '<=', $request->date('to'));
            }

            $breakdown = $breakdownQuery
                ->selectRaw('user_id, work_type_id, SUM(duration_minutes) as minutes')
                ->groupBy('user_id', 'work_type_id')
                ->get();

            $workTypeIds = $breakdown->pluck('work_type_id')->filter()->unique();
            $workTypes   = WorkType::whereIn('id', $workTypeIds)->get()->keyBy('id');

            foreach ($groups as &$group) {
                $userTotal = $group['minutes'];
                $subGroups = $breakdown
                    ->where('user_id', $group['id'])
                    ->map(function ($row) use ($workTypes, $userTotal) {
                        $wt = $row->work_type_id !== null ? $workTypes->get($row->work_type_id) : null;
                        return [
                            'id'      => $row->work_type_id !== null ? (int) $row->work_type_id : null,
                            'label'   => $wt?->name ?? 'No work type',
                            'minutes' => (int) $row->minutes,
                            'color'   => $wt?->color,
                            'percent' => $userTotal > 0
                                ? round($row->minutes / $userTotal * 100, 2)
                                : 0.0,
                        ];
                    })
                    ->sortBy([['minutes', 'desc'], ['label', 'asc']])
                    ->values()
                    ->all();

                $group['sub_groups'] = $subGroups;
            }
            unset($group);
        } else {
            foreach ($groups as &$group) {
                $group['sub_groups'] = null;
            }
            unset($group);
        }

        return response()->json([
            'total_minutes' => $total,
            'from'          => $request->input('from'),
            'to'            => $request->input('to'),
            'group_by'      => $groupBy,
            'groups'        => $groups,
        ]);
    }

    public function destroy(Project $project, TimeEntry $timeEntry)
    {
        abort_if($timeEntry->project_id !== $project->id, 404);
        $this->authorize('delete', $timeEntry);

        $timeEntry->delete();

        return response()->json(['message' => 'Time entry deleted.']);
    }
}
