<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $isMember = $project->members()->where('user_id', $request->user()->id)->exists();

        if (! $isMember) {
            return response()->json(['message' => 'You do not have access to this project.'], 403);
        }

        return $next($request);
    }
}
