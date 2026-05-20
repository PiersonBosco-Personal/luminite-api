<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTimerRequest;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

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

        return (new TimeEntryResource($entry))->response()->setStatusCode(201);
    }

    public function update(UpdateTimeEntryRequest $request, Project $project, TimeEntry $timeEntry)
    {
        abort_if($timeEntry->project_id !== $project->id, 404);
        $this->authorize('update', $timeEntry);

        $timeEntry->update($request->validated());
        $timeEntry->load('user', 'workType', 'task');

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

        return new TimeEntryResource($entry);
    }

    public function destroy(Project $project, TimeEntry $timeEntry)
    {
        abort_if($timeEntry->project_id !== $project->id, 404);
        $this->authorize('delete', $timeEntry);

        $timeEntry->delete();

        return response()->json(['message' => 'Time entry deleted.']);
    }
}
