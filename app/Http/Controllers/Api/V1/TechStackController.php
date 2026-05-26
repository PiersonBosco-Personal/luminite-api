<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechStackRequest;
use App\Http\Resources\TechStackResource;
use App\Models\Project;
use App\Models\TechStack;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class TechStackController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

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
        if ($request->parent_id) {
            abort_unless(
                $project->techStacks()->where('id', $request->parent_id)->exists(),
                422,
                'Parent tech stack does not belong to this project.'
            );
        }

        $techStack = $project->techStacks()->create($request->validated());

        $this->activity->log(
            projectId:    $project->id,
            userId:       auth()->id(),
            eventType:    'tech_stack.added',
            subjectType:  'tech_stack',
            subjectLabel: $techStack->name,
            subjectId:    $techStack->id,
            description:  auth()->user()->name . " added {$techStack->name} to tech stack",
        );

        return new TechStackResource($techStack->load('children'));
    }

    public function update(Request $request, Project $project, TechStack $techStack)
    {
        abort_if($techStack->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'version' => 'sometimes|nullable|string|max:50',
        ]);

        $oldDisplay = $techStack->version
            ? "{$techStack->name} v{$techStack->version}"
            : $techStack->name;

        $techStack->update($validated);
        $techStack->refresh();

        $newDisplay = $techStack->version
            ? "{$techStack->name} v{$techStack->version}"
            : $techStack->name;

        if ($newDisplay !== $oldDisplay) {
            $this->activity->log(
                projectId:    $project->id,
                userId:       auth()->id(),
                eventType:    'tech_stack.updated',
                subjectType:  'tech_stack',
                subjectLabel: $techStack->name,
                subjectId:    $techStack->id,
                description:  auth()->user()->name . " updated tech stack: {$oldDisplay} → {$newDisplay}",
                oldValue:     $oldDisplay,
                newValue:     $newDisplay,
                fieldChanged: 'version',
            );
        }

        return new TechStackResource($techStack->load('children'));
    }

    public function destroy(Project $project, TechStack $techStack)
    {
        abort_if($techStack->project_id !== $project->id, 404);

        $techStack->delete();

        return response()->json(['message' => 'Tech stack entry removed.']);
    }
}
