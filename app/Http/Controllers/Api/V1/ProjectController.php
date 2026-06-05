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
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(private ActivityLogService $activity) {}

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
        return DB::transaction(function () use ($request) {
            $project = Project::create([
                'owner_id'    => $request->user()->id,
                'name'        => $request->name,
                'description' => $request->description,
                'status'      => $request->status ?? 'active',
            ]);

            $project->members()->attach($request->user()->id, ['role' => 'owner']);

            $defaultWorkTypes = [
                'Development'   => 'blue',
                'Testing'       => 'green',
                'Design'        => 'purple',
                'Meeting'       => 'amber',
                'Documentation' => 'cyan',
                'Other'         => 'slate',
            ];
            foreach ($defaultWorkTypes as $name => $color) {
                $project->workTypes()->create(['name' => $name, 'color' => $color]);
            }

            $this->activity->log(
                projectId:    $project->id,
                userId:       $request->user()->id,
                eventType:    'project.created',
                subjectType:  'project',
                subjectLabel: $project->name,
                subjectId:    $project->id,
                description:  $request->user()->name . " created project {$project->name}",
            );

            return new ProjectResource($project->load('owner'));
        });
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

        $original = $project->only(array_keys($request->validated()));
        $project->update($request->validated());
        $project->load('owner');

        foreach ($project->getChanges() as $field => $newValue) {
            if ($field === 'updated_at') {
                continue;
            }

            $this->activity->log(
                projectId:    $project->id,
                userId:       $request->user()->id,
                eventType:    'project.updated',
                subjectType:  'project',
                subjectLabel: $project->name,
                subjectId:    $project->id,
                description:  $request->user()->name . " updated project {$field}",
                oldValue:     (string) ($original[$field] ?? ''),
                newValue:     (string) $newValue,
                fieldChanged: $field,
            );
        }

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

            $this->activity->log(
                projectId:    $project->id,
                userId:       $request->user()->id,
                eventType:    'project.member_added',
                subjectType:  'user',
                subjectLabel: $user->name,
                subjectId:    $user->id,
                description:  $request->user()->name . " added {$user->name} to the project",
            );

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

        $this->activity->log(
            projectId:    $project->id,
            userId:       $request->user()->id,
            eventType:    'project.member_invited',
            subjectType:  'invitation',
            subjectLabel: $invitation->email,
            subjectId:    $invitation->id,
            description:  $request->user()->name . " invited {$invitation->email} to the project",
        );

        return response()->json(['message' => 'Invitation sent.'], 202);
    }

    public function removeMember(Request $request, Project $project, User $user)
    {
        $this->authorize('manageMember', $project);

        if ($user->id === $project->owner_id) {
            return response()->json(['message' => 'Cannot remove the project owner.'], 422);
        }

        $project->members()->detach($user->id);

        $this->activity->log(
            projectId:    $project->id,
            userId:       $request->user()->id,
            eventType:    'project.member_removed',
            subjectType:  'user',
            subjectLabel: $user->name,
            subjectId:    $user->id,
            description:  $request->user()->name . " removed {$user->name} from the project",
        );

        return response()->json(['message' => 'Member removed.']);
    }
}
