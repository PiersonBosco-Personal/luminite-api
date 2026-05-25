<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\TaskCreated;
use App\Events\TaskDeleted;
use App\Events\TasksReordered;
use App\Events\TaskUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

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

        $task->load('assignee', 'labels');

        broadcast(new TaskCreated($task, $project->id))->toOthers();

        $this->activity->log(
            projectId:    $project->id,
            userId:       auth()->id(),
            eventType:    'task.created',
            subjectType:  'task',
            subjectLabel: $task->title,
            subjectId:    $task->id,
            description:  auth()->user()->name . " created task {$task->title}",
        );

        return new TaskResource($task);
    }

    public function show(Project $project, Task $task)
    {
        abort_if($task->project_id !== $project->id, 404);

        return new TaskResource(
            $task->load('assignee', 'labels', 'subtasks.assignee', 'notes', 'attachments.uploader')
        );
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task)
    {
        abort_if($task->project_id !== $project->id, 404);

        $validated = $request->validated();

        // Capture originals before mutation
        $originalStatus   = $task->status;
        $originalAssignee = $task->assigned_to;
        $originalDueDate  = $task->due_date?->toDateString();

        $task->update($validated);
        $task->load('assignee', 'labels');

        broadcast(new TaskUpdated($task, $project->id))->toOthers();

        $actorName = auth()->user()->name;

        // Status: only log completed/reopened
        if (isset($validated['status']) && $validated['status'] !== $originalStatus) {
            if ($validated['status'] === 'done') {
                $this->activity->log(
                    projectId:    $project->id,
                    userId:       auth()->id(),
                    eventType:    'task.completed',
                    subjectType:  'task',
                    subjectLabel: $task->title,
                    subjectId:    $task->id,
                    description:  "{$actorName} completed {$task->title}",
                );
            } elseif ($originalStatus === 'done') {
                $this->activity->log(
                    projectId:    $project->id,
                    userId:       auth()->id(),
                    eventType:    'task.reopened',
                    subjectType:  'task',
                    subjectLabel: $task->title,
                    subjectId:    $task->id,
                    description:  "{$actorName} reopened {$task->title}",
                );
            }
        }

        // Assignment
        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== $originalAssignee) {
            if ($validated['assigned_to'] === null) {
                $this->activity->log(
                    projectId:    $project->id,
                    userId:       auth()->id(),
                    eventType:    'task.unassigned',
                    subjectType:  'task',
                    subjectLabel: $task->title,
                    subjectId:    $task->id,
                    description:  "{$actorName} unassigned {$task->title}",
                );
            } else {
                $assigneeName = User::find($validated['assigned_to'])?->name ?? 'someone';
                $this->activity->log(
                    projectId:    $project->id,
                    userId:       auth()->id(),
                    eventType:    'task.assigned',
                    subjectType:  'task',
                    subjectLabel: $task->title,
                    subjectId:    $task->id,
                    description:  "{$actorName} assigned {$assigneeName} to {$task->title}",
                );
            }
        }

        // Due date
        $newDueDate = $task->due_date?->toDateString();
        if (array_key_exists('due_date', $validated) && $newDueDate !== $originalDueDate) {
            $oldDisplay = $originalDueDate ?? 'none';
            $newDisplay = $newDueDate ?? 'none';
            $this->activity->log(
                projectId:    $project->id,
                userId:       auth()->id(),
                eventType:    'task.due_date_changed',
                subjectType:  'task',
                subjectLabel: $task->title,
                subjectId:    $task->id,
                description:  "{$actorName} changed due date: {$oldDisplay} → {$newDisplay}",
                oldValue:     $originalDueDate,
                newValue:     $newDueDate,
                fieldChanged: 'due_date',
            );
        }

        return new TaskResource($task);
    }

    public function destroy(Project $project, Task $task)
    {
        abort_if($task->project_id !== $project->id, 404);

        $taskTitle = $task->title;
        $taskId    = $task->id;
        $task->delete();

        broadcast(new TaskDeleted($taskId, $project->id))->toOthers();

        $this->activity->log(
            projectId:    $project->id,
            userId:       auth()->id(),
            eventType:    'task.deleted',
            subjectType:  'task',
            subjectLabel: $taskTitle,
            subjectId:    null, // deleted — no reference
            description:  auth()->user()->name . " deleted task {$taskTitle}",
        );

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

        broadcast(new TasksReordered($project->id))->toOthers();

        return response()->json(['message' => 'Tasks reordered.']);
    }
}
