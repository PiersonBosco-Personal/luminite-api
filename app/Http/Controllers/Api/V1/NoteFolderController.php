<?php

namespace App\Http\Controllers\Api\V1;

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
            'name'     => 'required|string|max:255',
            'position' => 'sometimes|integer|min:0',
        ]);

        $position = $validated['position']
            ?? $project->noteFolders()->max('position') + 1;

        $folder = $project->noteFolders()->create([
            'created_by' => $request->user()->id,
            'name'       => $validated['name'],
            'position'   => $position,
        ]);

        return response()->json(['data' => $folder], 201);
    }

    public function update(Request $request, Project $project, NoteFolder $noteFolder)
    {
        abort_if($noteFolder->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'position' => 'sometimes|integer|min:0',
        ]);

        $noteFolder->update($validated);

        return response()->json(['data' => $noteFolder]);
    }

    public function destroy(Project $project, NoteFolder $noteFolder)
    {
        abort_if($noteFolder->project_id !== $project->id, 404);

        $noteFolder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }
}
