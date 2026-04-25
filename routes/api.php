<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LabelController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NoteFolderController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskSectionController;
use App\Http\Controllers\Api\V1\TechStackController;
use App\Http\Controllers\Api\V1\WidgetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);
    Route::get('/invitations/{token}',         [InvitationController::class, 'show']);
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user',    [AuthController::class, 'user']);

        // Widget catalog (all active widget types)
        Route::get('/widgets', [WidgetController::class, 'index']);

        // Dashboard widget delete (not project-scoped — frontend calls without project context)
        Route::delete('/dashboard-widgets/{dashboardWidget}', [DashboardController::class, 'destroy']);

        // Projects
        Route::get('/projects',       [ProjectController::class, 'index']);
        Route::post('/projects',      [ProjectController::class, 'store']);

        // Project-scoped routes (must be a member)
        Route::middleware('project.member')->group(function () {
            Route::get('/projects/{project}',    [ProjectController::class, 'show']);
            Route::put('/projects/{project}',    [ProjectController::class, 'update']);
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

            // Members
            Route::get('/projects/{project}/members',              [ProjectController::class, 'members']);
            Route::post('/projects/{project}/members',             [ProjectController::class, 'addMember']);
            Route::delete('/projects/{project}/members/{user}',    [ProjectController::class, 'removeMember']);

            // Tech Stack
            Route::get('/projects/{project}/tech-stack',                   [TechStackController::class, 'index']);
            Route::post('/projects/{project}/tech-stack',                  [TechStackController::class, 'store']);
            Route::patch('/projects/{project}/tech-stack/{techStack}',     [TechStackController::class, 'update']);
            Route::delete('/projects/{project}/tech-stack/{techStack}',    [TechStackController::class, 'destroy']);

            // Task Sections
            Route::get('/projects/{project}/sections',                       [TaskSectionController::class, 'index']);
            Route::post('/projects/{project}/sections',                      [TaskSectionController::class, 'store']);
            Route::put('/projects/{project}/sections/{section}',             [TaskSectionController::class, 'update']);
            Route::delete('/projects/{project}/sections/{section}',          [TaskSectionController::class, 'destroy']);
            Route::post('/projects/{project}/sections/reorder',              [TaskSectionController::class, 'reorder']);

            // Tasks
            Route::get('/projects/{project}/tasks',              [TaskController::class, 'index']);
            Route::post('/projects/{project}/tasks',             [TaskController::class, 'store']);
            Route::get('/projects/{project}/tasks/{task}',       [TaskController::class, 'show']);
            Route::put('/projects/{project}/tasks/{task}',       [TaskController::class, 'update']);
            Route::delete('/projects/{project}/tasks/{task}',    [TaskController::class, 'destroy']);
            Route::post('/projects/{project}/tasks/reorder',     [TaskController::class, 'reorder']);

            // Note Folders
            Route::get('/projects/{project}/note-folders',                          [NoteFolderController::class, 'index']);
            Route::post('/projects/{project}/note-folders',                         [NoteFolderController::class, 'store']);
            Route::put('/projects/{project}/note-folders/{noteFolder}',             [NoteFolderController::class, 'update']);
            Route::delete('/projects/{project}/note-folders/{noteFolder}',          [NoteFolderController::class, 'destroy']);

            // Notes
            Route::get('/projects/{project}/notes',                      [NoteController::class, 'index']);
            Route::post('/projects/{project}/notes',                     [NoteController::class, 'store']);
            Route::get('/projects/{project}/notes/{note}',               [NoteController::class, 'show']);
            Route::put('/projects/{project}/notes/{note}',               [NoteController::class, 'update']);
            Route::delete('/projects/{project}/notes/{note}',            [NoteController::class, 'destroy']);
            Route::patch('/projects/{project}/notes/{note}/pin',         [NoteController::class, 'togglePin']);

            // Labels
            Route::get('/projects/{project}/labels',                              [LabelController::class, 'index']);
            Route::post('/projects/{project}/labels',                             [LabelController::class, 'store']);
            Route::put('/projects/{project}/labels/{label}',                      [LabelController::class, 'update']);
            Route::delete('/projects/{project}/labels/{label}',                   [LabelController::class, 'destroy']);
            Route::post('/projects/{project}/labels/{label}/tasks/attach',        [LabelController::class, 'attachToTask']);
            Route::delete('/projects/{project}/labels/{label}/tasks/detach',      [LabelController::class, 'detachFromTask']);
            Route::post('/projects/{project}/labels/{label}/notes/attach',        [LabelController::class, 'attachToNote']);
            Route::delete('/projects/{project}/labels/{label}/notes/detach',      [LabelController::class, 'detachFromNote']);

            // Dashboard widgets (per-user layout for this project)
            Route::get('/projects/{project}/dashboard-widgets',         [DashboardController::class, 'index']);
            Route::post('/projects/{project}/dashboard-widgets',        [DashboardController::class, 'store']);
            Route::post('/projects/{project}/dashboard-widgets/sync',   [DashboardController::class, 'sync']);

            // AI (stubs — Phase 4)
            Route::get('/projects/{project}/ai/conversations',    [AiController::class, 'index']);
            Route::post('/projects/{project}/ai/conversations',   [AiController::class, 'store']);
        });
    });
});
