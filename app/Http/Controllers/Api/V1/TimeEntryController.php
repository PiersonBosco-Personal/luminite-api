<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
}
