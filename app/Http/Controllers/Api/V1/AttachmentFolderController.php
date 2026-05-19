<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttachmentFolder;
use App\Models\Project;
use Illuminate\Http\Request;

class AttachmentFolderController extends Controller
{
    public function index(Project $project)
    {
        $folders = $project->attachmentFolders()->orderBy('position')->get();

        return response()->json(['data' => $folders]);
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'position'  => 'sometimes|integer|min:0',
            'parent_id' => 'nullable|exists:attachment_folders,id',
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = AttachmentFolder::find($validated['parent_id']);
            abort_if($parent->project_id !== $project->id, 422, 'Parent folder does not belong to this project.');
            abort_if($parent->parent_id !== null, 422, 'Sub-folders cannot be nested more than one level deep.');
        }

        $parentId = $validated['parent_id'] ?? null;
        $position = $validated['position']
            ?? $project->attachmentFolders()->where('parent_id', $parentId)->max('position') + 1;

        $folder = $project->attachmentFolders()->create([
            'created_by' => $request->user()->id,
            'name'       => $validated['name'],
            'position'   => $position,
            'parent_id'  => $parentId,
        ]);

        return response()->json(['data' => $folder], 201);
    }

    public function update(Request $request, Project $project, AttachmentFolder $attachmentFolder)
    {
        abort_if($attachmentFolder->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'position' => 'sometimes|integer|min:0',
        ]);

        $attachmentFolder->update($validated);

        return response()->json(['data' => $attachmentFolder]);
    }

    public function destroy(Project $project, AttachmentFolder $attachmentFolder)
    {
        abort_if($attachmentFolder->project_id !== $project->id, 404);

        // Move files in this folder up to its parent (or null = project root)
        $attachmentFolder->attachments()->update([
            'folder_id' => $attachmentFolder->parent_id,
        ]);

        $attachmentFolder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }
}
