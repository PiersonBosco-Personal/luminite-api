<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteAttachmentFile;
use App\Models\Attachment;
use App\Models\AttachmentFolder;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    // ── Upload ──────────────────────────────────────────────────────────────

    public function storeForProject(Request $request, Project $project)
    {
        $validated = $request->validate([
            'file'      => 'required|file|max:51200',
            'folder_id' => 'nullable|exists:attachment_folders,id',
        ]);

        if (!empty($validated['folder_id'])) {
            $folder = AttachmentFolder::find($validated['folder_id']);
            abort_if($folder->project_id !== $project->id, 422, 'Folder does not belong to this project.');
        }

        return $this->upload($request, $project, $project->id, $validated['folder_id'] ?? null);
    }

    public function storeForTask(Request $request, Project $project, Task $task)
    {
        abort_if($task->project_id !== $project->id, 404);
        $request->validate(['file' => 'required|file|max:51200']);

        return $this->upload($request, $task, $project->id, null);
    }

    public function storeForNote(Request $request, Project $project, Note $note)
    {
        abort_if($note->project_id !== $project->id, 404);
        $request->validate(['file' => 'required|file|max:51200']);

        return $this->upload($request, $note, $project->id, null);
    }

    // ── List (project-level only) ────────────────────────────────────────────

    public function indexForProject(Request $request, Project $project)
    {
        $query = $project->attachments()->with('uploader')->orderBy('created_at', 'desc');

        if ($request->has('folder_id')) {
            $folderId = $request->query('folder_id');
            $folderId === 'null' || $folderId === null
                ? $query->whereNull('folder_id')
                : $query->where('folder_id', (int) $folderId);
        }

        return response()->json(['data' => $query->get()]);
    }

    // ── Download / stream ────────────────────────────────────────────────────

    public function show(Request $request, Attachment $attachment)
    {
        $project = $attachment->getProject();
        abort_unless(
            $project->members()->where('user_id', $request->user()->id)->exists(),
            403
        );

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function destroy(Request $request, Attachment $attachment)
    {
        $project = $attachment->getProject();

        $isUploader = $attachment->uploaded_by === $request->user()->id;
        $isOwner    = $project->owner_id === $request->user()->id;
        abort_unless($isUploader || $isOwner, 403);

        $disk = $attachment->disk;
        $path = $attachment->path;

        $attachment->delete();

        DeleteAttachmentFile::dispatch($disk, $path);

        return response()->json(['message' => 'Attachment deleted.']);
    }

    // ── Private helper ───────────────────────────────────────────────────────

    private function upload(Request $request, Model $attachable, int $projectId, ?int $folderId)
    {
        $file         = $request->file('file');
        $uuid         = Str::uuid()->toString();
        $originalName = $file->getClientOriginalName();
        $storagePath  = "attachments/{$projectId}/{$uuid}/{$originalName}";

        Storage::disk('local')->putFileAs(
            "attachments/{$projectId}/{$uuid}",
            $file,
            $originalName
        );

        $attachment = $attachable->attachments()->create([
            'uploaded_by'   => $request->user()->id,
            'folder_id'     => $folderId,
            'filename'      => $originalName,
            'original_name' => $originalName,
            'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            'path'          => $storagePath,
            'disk'          => 'local',
            'size'          => $file->getSize(),
            'url'           => '',
        ]);

        $attachment->update(['url' => "/v1/attachments/{$attachment->id}"]);

        $attachment->load('uploader');

        return response()->json(['data' => $attachment], 201);
    }
}
