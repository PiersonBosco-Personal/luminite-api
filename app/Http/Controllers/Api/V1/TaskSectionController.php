<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskSectionRequest;
use App\Http\Requests\UpdateTaskSectionRequest;
use App\Http\Resources\TaskSectionResource;
use App\Models\Project;
use App\Models\TaskSection;
use Illuminate\Http\Request;

class TaskSectionController extends Controller
{
    public function index(Project $project)
    {
        $sections = $project->taskSections()
            ->orderBy('position')
            ->get();

        return TaskSectionResource::collection($sections);
    }

    public function store(StoreTaskSectionRequest $request, Project $project)
    {
        $position = $request->position
            ?? $project->taskSections()->max('position') + 1;

        $section = $project->taskSections()->create([
            'name'     => $request->name,
            'position' => $position,
        ]);

        return new TaskSectionResource($section);
    }

    public function update(UpdateTaskSectionRequest $request, Project $project, TaskSection $section)
    {
        abort_if($section->project_id !== $project->id, 404);

        $section->update($request->validated());

        return new TaskSectionResource($section);
    }

    public function destroy(Project $project, TaskSection $section)
    {
        abort_if($section->project_id !== $project->id, 404);

        $section->delete();

        return response()->json(['message' => 'Section deleted.']);
    }

    public function reorder(Request $request, Project $project)
    {
        $request->validate([
            'sections'          => 'required|array',
            'sections.*.id'     => 'required|integer|exists:task_sections,id',
            'sections.*.position' => 'required|integer|min:0',
        ]);

        foreach ($request->sections as $item) {
            $project->taskSections()
                ->where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Sections reordered.']);
    }
}
