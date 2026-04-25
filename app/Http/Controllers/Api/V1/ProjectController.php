<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ProjectUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\UserResource;
use App\Mail\ProjectInvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = $request->user()
            ->projects()
            ->with('owner')
            ->withCount('members')
            ->get();

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request)
    {
        $project = Project::create([
            'owner_id'    => $request->user()->id,
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => $request->status ?? 'active',
        ]);

        // Owner is always a member
        $project->members()->attach($request->user()->id, ['role' => 'owner']);

        return new ProjectResource($project->load('owner'));
    }

    public function show(Project $project)
    {
        return new ProjectResource(
            $project->load('owner', 'members', 'techStacks')
        );
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $project->update($request->validated());
        $project->load('owner');

        broadcast(new ProjectUpdated($project, $project->id))->toOthers();

        return new ProjectResource($project);
    }

    public function destroy(Request $request, Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    public function members(Project $project)
    {
        $members = $project->members()->get();

        return UserResource::collection($members)->additional([
            'meta' => [
                'roles' => $members->mapWithKeys(fn($u) => [
                    $u->id => $u->pivot->role,
                ]),
            ],
        ]);
    }

    public function addMember(AddProjectMemberRequest $request, Project $project)
    {
        $this->authorize('manageMember', $project);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            if ($project->members()->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'User is already a member.'], 409);
            }

            $project->members()->attach($user->id, ['role' => $request->role ?? 'member']);

            return new UserResource($user);
        }

        // User doesn't exist — send an invite
        $alreadyInvited = ProjectInvitation::pending()
            ->where('project_id', $project->id)
            ->where('email', $request->email)
            ->exists();

        if ($alreadyInvited) {
            return response()->json(['message' => 'An invitation has already been sent to this email.'], 409);
        }

        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'invited_by' => $request->user()->id,
            'email'      => $request->email,
            'token'      => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load('project', 'inviter');

        Mail::to($request->email)->send(new ProjectInvitationMail($invitation));

        return response()->json(['message' => 'Invitation sent.'], 202);
    }

    public function removeMember(Request $request, Project $project, User $user)
    {
        $this->authorize('manageMember', $project);

        if ($user->id === $project->owner_id) {
            return response()->json(['message' => 'Cannot remove the project owner.'], 422);
        }

        $project->members()->detach($user->id);

        return response()->json(['message' => 'Member removed.']);
    }
}
