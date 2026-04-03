<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Project $project)
    {
        $notes = $project->notes()
            ->with('author', 'labels')
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        return NoteResource::collection($notes);
    }

    public function store(StoreNoteRequest $request, Project $project)
    {
        $note = $project->notes()->create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        return new NoteResource($note->load('author', 'labels'));
    }

    public function show(Project $project, Note $note)
    {
        abort_if($note->project_id !== $project->id, 404);

        return new NoteResource($note->load('author', 'labels'));
    }

    public function update(UpdateNoteRequest $request, Project $project, Note $note)
    {
        abort_if($note->project_id !== $project->id, 404);

        $note->update($request->validated());

        return new NoteResource($note->load('author', 'labels'));
    }

    public function destroy(Project $project, Note $note)
    {
        abort_if($note->project_id !== $project->id, 404);

        $note->delete();

        return response()->json(['message' => 'Note deleted.']);
    }

    public function togglePin(Project $project, Note $note)
    {
        abort_if($note->project_id !== $project->id, 404);

        $note->update(['is_pinned' => ! $note->is_pinned]);

        return new NoteResource($note->load('author', 'labels'));
    }
}
