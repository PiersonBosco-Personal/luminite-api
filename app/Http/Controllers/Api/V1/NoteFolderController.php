<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NoteFolderCreated;
use App\Events\NoteFolderDeleted;
use App\Events\NoteFolderUpdated;
use App\Http\Controllers\Controller;
use App\Models\NoteFolder;
use App\Models\Project;
use Illuminate\Http\Request;

class NoteFolderController extends Controller
{
    public function index(Project $project)
    {
        $folders = $project->noteFolders()->orderBy('position')->get();

        return response()->json(['data' => $folders]);
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'position'  => 'sometimes|integer|min:0',
            'parent_id' => 'nullable|exists:note_folders,id',
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = NoteFolder::find($validated['parent_id']);
            abort_if($parent->project_id !== $project->id, 422, 'Parent folder does not belong to this project.');
            abort_if($parent->parent_id !== null, 422, 'Sub-folders cannot be nested more than one level deep.');
        }

        $parentId = $validated['parent_id'] ?? null;
        $position = $validated['position']
            ?? $project->noteFolders()->where('parent_id', $parentId)->max('position') + 1;

        $folder = $project->noteFolders()->create([
            'created_by' => $request->user()->id,
            'name'       => $validated['name'],
            'position'   => $position,
            'parent_id'  => $parentId,
        ]);

        broadcast(new NoteFolderCreated($folder, $project->id))->toOthers();

        return response()->json(['data' => $folder], 201);
    }

    public function update(Request $request, Project $project, NoteFolder $noteFolder)
    {
        abort_if($noteFolder->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'position'  => 'sometimes|integer|min:0',
            'parent_id' => 'sometimes|nullable|exists:note_folders,id',
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = NoteFolder::find($validated['parent_id']);
            abort_if($parent->project_id !== $project->id, 422, 'Parent folder does not belong to this project.');
            abort_if($parent->parent_id !== null, 422, 'Sub-folders cannot be nested more than one level deep.');
        }

        $noteFolder->update($validated);

        broadcast(new NoteFolderUpdated($noteFolder, $project->id))->toOthers();

        return response()->json(['data' => $noteFolder]);
    }

    public function destroy(Project $project, NoteFolder $noteFolder)
    {
        abort_if($noteFolder->project_id !== $project->id, 404);

        $folderId = $noteFolder->id;
        $noteFolder->delete();

        broadcast(new NoteFolderDeleted($folderId, $project->id))->toOthers();

        return response()->json(['message' => 'Folder deleted.']);
    }
}
