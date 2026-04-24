<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NoteCreated;
use App\Events\NoteDeleted;
use App\Events\NoteUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $query = $project->notes()->with('author', 'labels');

        if ($request->has('folder_id')) {
            $folderId = $request->query('folder_id');
            $folderId === 'null' || $folderId === null
                ? $query->whereNull('folder_id')
                : $query->where('folder_id', (int) $folderId);
        }

        $notes = $query->orderBy('position')->orderBy('id')->get();

        return NoteResource::collection($notes);
    }

    public function store(StoreNoteRequest $request, Project $project)
    {
        $validated = $request->validated();
        $folderId  = $validated['folder_id'] ?? null;

        $maxPosition = $project->notes()
            ->when(
                $folderId !== null,
                fn ($q) => $q->where('folder_id', $folderId),
                fn ($q) => $q->whereNull('folder_id')
            )
            ->max('position') ?? -1;

        $note = $project->notes()->create(array_merge(
            $validated,
            ['created_by' => $request->user()->id, 'position' => $maxPosition + 1]
        ));

        $note->load('author', 'labels');

        broadcast(new NoteCreated($note, $project->id))->toOthers();

        return new NoteResource($note);
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
        $note->load('author', 'labels');

        broadcast(new NoteUpdated($note, $project->id))->toOthers();

        return new NoteResource($note);
    }

    public function destroy(Project $project, Note $note)
    {
        abort_if($note->project_id !== $project->id, 404);

        $noteId = $note->id;
        $note->delete();

        broadcast(new NoteDeleted($noteId, $project->id))->toOthers();

        return response()->json(['message' => 'Note deleted.']);
    }

    public function togglePin(Project $project, Note $note)
    {
        abort_if($note->project_id !== $project->id, 404);

        $note->update(['is_pinned' => ! $note->is_pinned]);
        $note->load('author', 'labels');

        broadcast(new NoteUpdated($note, $project->id))->toOthers();

        return new NoteResource($note);
    }
}
