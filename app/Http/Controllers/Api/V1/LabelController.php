<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabelRequest;
use App\Http\Requests\UpdateLabelRequest;
use App\Http\Resources\LabelResource;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    public function index(Project $project)
    {
        return LabelResource::collection($project->labels);
    }

    public function store(StoreLabelRequest $request, Project $project)
    {
        $label = $project->labels()->create($request->validated());

        return new LabelResource($label);
    }

    public function update(UpdateLabelRequest $request, Project $project, Label $label)
    {
        abort_if($label->project_id !== $project->id, 404);

        $label->update($request->validated());

        return new LabelResource($label);
    }

    public function destroy(Project $project, Label $label)
    {
        abort_if($label->project_id !== $project->id, 404);

        $label->delete();

        return response()->json(['message' => 'Label deleted.']);
    }

    public function attachToTask(Request $request, Project $project, Label $label)
    {
        abort_if($label->project_id !== $project->id, 404);

        $request->validate([
            'task_id' => [
                'required',
                'integer',
                Rule::exists('tasks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $task = $project->tasks()->findOrFail($request->task_id);
        $task->labels()->syncWithoutDetaching([$label->id]);

        return response()->json(['message' => 'Label attached.']);
    }

    public function detachFromTask(Request $request, Project $project, Label $label)
    {
        abort_if($label->project_id !== $project->id, 404);

        $request->validate(['task_id' => 'required|integer|exists:tasks,id']);

        $task = $project->tasks()->findOrFail($request->task_id);
        $task->labels()->detach($label->id);

        return response()->json(['message' => 'Label detached.']);
    }

    public function attachToNote(Request $request, Project $project, Label $label)
    {
        abort_if($label->project_id !== $project->id, 404);

        $request->validate(['note_id' => 'required|integer|exists:notes,id']);

        $note = $project->notes()->findOrFail($request->note_id);
        $note->labels()->syncWithoutDetaching([$label->id]);

        return response()->json(['message' => 'Label attached.']);
    }

    public function detachFromNote(Request $request, Project $project, Label $label)
    {
        abort_if($label->project_id !== $project->id, 404);

        $request->validate(['note_id' => 'required|integer|exists:notes,id']);

        $note = $project->notes()->findOrFail($request->note_id);
        $note->labels()->detach($label->id);

        return response()->json(['message' => 'Label detached.']);
    }
}
