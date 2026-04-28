<?php

use App\Models\Note;
use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Private project channel
|--------------------------------------------------------------------------
| Any authenticated project member may subscribe.
*/
Broadcast::channel('project.{projectId}', function ($user, int $projectId) {
    $project = Project::find($projectId);

    if (! $project) {
        return false;
    }

    return $project->members()->where('user_id', $user->id)->exists();
});

/*
|--------------------------------------------------------------------------
| Presence project channel
|--------------------------------------------------------------------------
| Returns user identity for the online member list.
| Must return an array (not just true) for presence channels.
*/
Broadcast::channel('presence-project.{projectId}', function ($user, int $projectId) {
    $project = Project::find($projectId);

    if (! $project) {
        return false;
    }

    if (! $project->members()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return [
        'id'   => $user->id,
        'name' => $user->name,
    ];
});

/*
|--------------------------------------------------------------------------
| Presence note channel
|--------------------------------------------------------------------------
| Tracks which users are currently viewing/editing a specific note.
*/
Broadcast::channel('presence-note.{noteId}', function ($user, int $noteId) {
    $note = Note::find($noteId);

    if (! $note) {
        return false;
    }

    if (! $note->project->members()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return [
        'id'   => $user->id,
        'name' => $user->name,
    ];
});
