<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechStackRequest;
use App\Http\Resources\TechStackResource;
use App\Models\Project;
use App\Models\TechStack;
use Illuminate\Http\Request;

class TechStackController extends Controller
{
    public function index(Project $project)
    {
        $techStacks = $project->techStacks()
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return TechStackResource::collection($techStacks);
    }

    public function store(StoreTechStackRequest $request, Project $project)
    {
        // Ensure parent_id belongs to this project
        if ($request->parent_id) {
            abort_unless(
                $project->techStacks()->where('id', $request->parent_id)->exists(),
                422,
                'Parent tech stack does not belong to this project.'
            );
        }

        $techStack = $project->techStacks()->create($request->validated());

        return new TechStackResource($techStack->load('children'));
    }

    public function update(Request $request, Project $project, TechStack $techStack)
    {
        abort_if($techStack->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'version' => 'sometimes|nullable|string|max:50',
        ]);

        $techStack->update($validated);

        return new TechStackResource($techStack->load('children'));
    }

    public function destroy(Project $project, TechStack $techStack)
    {
        abort_if($techStack->project_id !== $project->id, 404);

        $techStack->delete();

        return response()->json(['message' => 'Tech stack entry removed.']);
    }
}
