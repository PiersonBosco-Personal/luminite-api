<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\InvitationCreated;
use App\Events\InvitationStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectInvitationResource;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ProjectInvitationController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

    public function index(Request $request, Project $project)
    {
        $this->authorize('manageMember', $project);

        $invitations = $project->invitations()
            ->whereNull('accepted_at')
            ->with('inviter')
            ->latest()
            ->get();

        return ProjectInvitationResource::collection($invitations);
    }

    public function resend(Request $request, Project $project, ProjectInvitation $invitation)
    {
        $this->authorize('manageMember', $project);
        abort_unless($invitation->project_id === $project->id, 404);

        $invitation->update([
            'expires_at'  => now()->addDays(7),
            'declined_at' => null,
            'accepted_at' => null,
        ]);

        $invitation->load('project', 'inviter');

        $recipient = User::where('email', $invitation->email)->first();
        Mail::to($invitation->email)->send(new ProjectInvitationMail($invitation, (bool) $recipient));

        $this->activity->log(
            projectId: $project->id,
            userId: $request->user()->id,
            eventType: 'project.invite_resent',
            subjectType: 'invitation',
            subjectLabel: $invitation->email,
            subjectId: $invitation->id,
            description: $request->user()->name . " resent the invitation to {$invitation->email}",
        );

        if ($recipient) {
            broadcast(new InvitationCreated($invitation, $recipient->id));
        }

        return new ProjectInvitationResource($invitation);
    }

    public function destroy(Request $request, Project $project, ProjectInvitation $invitation)
    {
        $this->authorize('manageMember', $project);
        abort_unless($invitation->project_id === $project->id, 404);

        $email = $invitation->email;
        $invitation->delete();

        $this->activity->log(
            projectId: $project->id,
            userId: $request->user()->id,
            eventType: 'project.invite_cancelled',
            subjectType: 'invitation',
            subjectLabel: $email,
            subjectId: $invitation->id,
            description: $request->user()->name . " cancelled the invitation to {$email}",
        );

        broadcast(new InvitationStatusChanged($invitation, $project->id));

        return response()->json(['message' => 'Invitation cancelled.']);
    }
}
