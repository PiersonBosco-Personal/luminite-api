<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkTypeRequest;
use App\Http\Requests\UpdateWorkTypeRequest;
use App\Http\Resources\WorkTypeResource;
use App\Models\Project;
use App\Models\WorkType;

class WorkTypeController extends Controller
{
    public function index(Project $project)
    {
        $workTypes = $project->workTypes()
            ->withCount('timeEntries')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return WorkTypeResource::collection($workTypes);
    }

    public function store(StoreWorkTypeRequest $request, Project $project)
    {
        $workType = $project->workTypes()->create($request->validated());

        return new WorkTypeResource($workType);
    }

    public function update(UpdateWorkTypeRequest $request, Project $project, WorkType $workType)
    {
        abort_if($workType->project_id !== $project->id, 404);

        $workType->update($request->validated());

        return new WorkTypeResource($workType);
    }

    public function destroy(Project $project, WorkType $workType)
    {
        abort_if($workType->project_id !== $project->id, 404);

        $workType->delete();

        return response()->json(['message' => 'Work type deleted.']);
    }
}
