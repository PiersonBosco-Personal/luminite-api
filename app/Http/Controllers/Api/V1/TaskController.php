<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Project $project)
    {
        $tasks = $project->tasks()
            ->with('assignee', 'labels')
            ->withCount('subtasks')
            ->orderBy('position')
            ->get();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, Project $project)
    {
        $position = $request->position
            ?? $project->tasks()->where('section_id', $request->section_id)->max('position') + 1;

        $task = $project->tasks()->create(array_merge(
            $request->validated(),
            ['position' => $position]
        ));

        return new TaskResource($task->load('assignee', 'labels'));
    }

    public function show(Project $project, Task $task)
    {
        abort_if($task->project_id !== $project->id, 404);

        return new TaskResource(
            $task->load('assignee', 'labels', 'subtasks', 'notes')
        );
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task)
    {
        abort_if($task->project_id !== $project->id, 404);

        $task->update($request->validated());

        return new TaskResource($task->load('assignee', 'labels'));
    }

    public function destroy(Project $project, Task $task)
    {
        abort_if($task->project_id !== $project->id, 404);

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    public function reorder(Request $request, Project $project)
    {
        $request->validate([
            'tasks'              => 'required|array',
            'tasks.*.id'         => 'required|integer|exists:tasks,id',
            'tasks.*.section_id' => 'required|integer|exists:task_sections,id',
            'tasks.*.position'   => 'required|integer|min:0',
        ]);

        foreach ($request->tasks as $item) {
            $project->tasks()
                ->where('id', $item['id'])
                ->update([
                    'section_id' => $item['section_id'],
                    'position'   => $item['position'],
                ]);
        }

        return response()->json(['message' => 'Tasks reordered.']);
    }
}
