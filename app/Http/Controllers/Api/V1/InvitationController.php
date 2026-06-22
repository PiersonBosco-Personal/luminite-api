<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\InvitationStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\RespondToInvitationRequest;
use App\Http\Resources\ProjectInvitationResource;
use App\Models\ProjectInvitation;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

    public function index(Request $request)
    {
        $invitations = ProjectInvitation::pending()
            ->where('email', $request->user()->email)
            ->with('project', 'inviter')
            ->latest()
            ->get();

        return ProjectInvitationResource::collection($invitations);
    }

    public function accept(RespondToInvitationRequest $request, ProjectInvitation $invitation)
    {
        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation is no longer valid.'], 410);
        }

        $user    = $request->user();
        $project = $invitation->project;

        if (! $project->members()->where('user_id', $user->id)->exists()) {
            $project->members()->attach($user->id, ['role' => 'member']);
        }

        $invitation->update(['accepted_at' => now()]);

        $this->activity->log(
            projectId: $project->id,
            userId: $user->id,
            eventType: 'project.member_joined',
            subjectType: 'user',
            subjectLabel: $user->name,
            subjectId: $user->id,
            description: "{$user->name} accepted an invitation and joined the project",
        );

        broadcast(new InvitationStatusChanged($invitation, $project->id));

        return response()->json([
            'message'    => 'Invitation accepted.',
            'project_id' => $project->id,
        ]);
    }

    public function decline(RespondToInvitationRequest $request, ProjectInvitation $invitation)
    {
        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation is no longer valid.'], 410);
        }

        $invitation->update(['declined_at' => now()]);

        $this->activity->log(
            projectId: $invitation->project_id,
            userId: $request->user()->id,
            eventType: 'project.invite_declined',
            subjectType: 'invitation',
            subjectLabel: $invitation->email,
            subjectId: $invitation->id,
            description: $request->user()->name . " declined an invitation to the project",
        );

        broadcast(new InvitationStatusChanged($invitation, $invitation->project_id));

        return response()->json(['message' => 'Invitation declined.']);
    }
}
