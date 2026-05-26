<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\SectionCreated;
use App\Events\SectionDeleted;
use App\Events\SectionUpdated;
use App\Events\SectionsReordered;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskSectionRequest;
use App\Http\Requests\UpdateTaskSectionRequest;
use App\Http\Resources\TaskSectionResource;
use App\Models\Project;
use App\Models\TaskSection;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class TaskSectionController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

    public function index(Project $project)
    {
        $sections = $project->taskSections()
            ->with(['tasks' => fn($q) => $q
                ->with('assignee', 'labels')
                ->withCount('subtasks')
                ->whereNull('parent_task_id')
                ->orderBy('position')
            ])
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

        broadcast(new SectionCreated($section, $project->id))->toOthers();

        $this->activity->log(
            projectId:    $project->id,
            userId:       auth()->id(),
            eventType:    'section.created',
            subjectType:  'section',
            subjectLabel: $section->name,
            subjectId:    $section->id,
            description:  auth()->user()->name . " created section {$section->name}",
        );

        return new TaskSectionResource($section);
    }

    public function update(UpdateTaskSectionRequest $request, Project $project, TaskSection $section)
    {
        abort_if($section->project_id !== $project->id, 404);

        $oldName = $section->name;
        $section->update($request->validated());

        broadcast(new SectionUpdated($section, $project->id))->toOthers();

        if ($section->name !== $oldName) {
            $this->activity->log(
                projectId:    $project->id,
                userId:       auth()->id(),
                eventType:    'section.renamed',
                subjectType:  'section',
                subjectLabel: $section->name,
                subjectId:    $section->id,
                description:  auth()->user()->name . " renamed section: {$oldName} → {$section->name}",
                oldValue:     $oldName,
                newValue:     $section->name,
                fieldChanged: 'name',
            );
        }

        return new TaskSectionResource($section);
    }

    public function destroy(Project $project, TaskSection $section)
    {
        abort_if($section->project_id !== $project->id, 404);

        $sectionName = $section->name;
        $sectionId   = $section->id;
        $section->delete();

        broadcast(new SectionDeleted($sectionId, $project->id))->toOthers();

        $this->activity->log(
            projectId:    $project->id,
            userId:       auth()->id(),
            eventType:    'section.deleted',
            subjectType:  'section',
            subjectLabel: $sectionName,
            subjectId:    null,
            description:  auth()->user()->name . " deleted section {$sectionName}",
        );

        return response()->json(['message' => 'Section deleted.']);
    }

    public function reorder(Request $request, Project $project)
    {
        $request->validate([
            'sections'            => 'required|array',
            'sections.*.id'       => 'required|integer|exists:task_sections,id',
            'sections.*.position' => 'required|integer|min:0',
        ]);

        foreach ($request->sections as $item) {
            $project->taskSections()
                ->where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }

        broadcast(new SectionsReordered($project->id))->toOthers();

        return response()->json(['message' => 'Sections reordered.']);
    }
}
