<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechStackRequest;
use App\Http\Resources\TechStackResource;
use App\Models\Project;
use App\Models\TechStack;

class TechStackController extends Controller
{
    public function index(Project $project)
    {
        return TechStackResource::collection($project->techStacks);
    }

    public function store(StoreTechStackRequest $request, Project $project)
    {
        $techStack = $project->techStacks()->create($request->validated());

        return new TechStackResource($techStack);
    }

    public function destroy(Project $project, TechStack $techStack)
    {
        abort_if($techStack->project_id !== $project->id, 404);

        $techStack->delete();

        return response()->json(['message' => 'Tech stack entry removed.']);
    }
}
