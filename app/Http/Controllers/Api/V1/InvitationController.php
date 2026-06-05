<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

    public function show(string $token)
    {
        $invitation = ProjectInvitation::pending()
            ->where('token', $token)
            ->with('project', 'inviter')
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'This invite link is invalid or has expired.'], 404);
        }

        return response()->json([
            'data' => [
                'email'        => $invitation->email,
                'project_id'   => $invitation->project->id,
                'project_name' => $invitation->project->name,
                'inviter_name' => $invitation->inviter->name,
            ],
        ]);
    }

    public function accept(string $token, Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $invitation = ProjectInvitation::pending()
            ->where('token', $token)
            ->with('project')
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'This invite link is invalid or has expired.'], 404);
        }

        if (User::where('email', $invitation->email)->exists()) {
            return response()->json(['message' => 'An account with this email already exists. Please log in.'], 409);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $invitation->email,
            'password' => $request->password,
        ]);

        $invitation->project->members()->attach($user->id, ['role' => 'member']);

        $invitation->update(['accepted_at' => now()]);

        $this->activity->log(
            projectId:    $invitation->project->id,
            userId:       $user->id,
            eventType:    'project.member_joined',
            subjectType:  'user',
            subjectLabel: $user->name,
            subjectId:    $user->id,
            description:  "{$user->name} accepted an invitation and joined the project",
        );

        $authToken = $user->createToken('luminite-app')->plainTextToken;

        return response()->json([
            'token'      => $authToken,
            'user'       => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'project_id' => $invitation->project->id,
        ]);
    }
}
