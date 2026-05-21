<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkTypeRequest;
use App\Http\Requests\UpdateWorkTypeRequest;
use App\Http\Resources\WorkTypeResource;
use App\Models\Project;
use App\Models\WorkType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $data = $request->validated();
        $name = $data['name'];

        // Names are unique per project (case-insensitive). If an inactive type
        // with this name exists, reactivate it instead of creating a duplicate
        // row — this lets users "recreate" a hidden type and keep its history.
        $existing = $project->workTypes()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderBy('is_active', 'desc')
            ->orderByDesc('updated_at')
            ->first();

        if ($existing && $existing->is_active) {
            throw ValidationException::withMessages([
                'name' => ['A work type with this name already exists.'],
            ]);
        }

        if ($existing) {
            $existing->update([
                'is_active' => true,
                'name'      => $name,
            ]);
            $existing->loadCount('timeEntries');

            return new WorkTypeResource($existing);
        }

        $workType = $project->workTypes()->create($data);
        $workType->loadCount('timeEntries');

        return (new WorkTypeResource($workType))->response()->setStatusCode(201);
    }

    public function update(UpdateWorkTypeRequest $request, Project $project, WorkType $workType)
    {
        abort_if($workType->project_id !== $project->id, 404);

        $data = $request->validated();

        if (isset($data['name']) && Str::lower($data['name']) !== Str::lower($workType->name)) {
            $conflict = $project->workTypes()
                ->whereRaw('LOWER(name) = ?', [Str::lower($data['name'])])
                ->where('id', '!=', $workType->id)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'name' => ['A work type with this name already exists.'],
                ]);
            }
        }

        $workType->update($data);
        $workType->loadCount('timeEntries');

        return new WorkTypeResource($workType);
    }

    public function destroy(Project $project, WorkType $workType)
    {
        abort_if($workType->project_id !== $project->id, 404);

        $workType->delete();

        return response()->json(['message' => 'Work type deleted.']);
    }
}
