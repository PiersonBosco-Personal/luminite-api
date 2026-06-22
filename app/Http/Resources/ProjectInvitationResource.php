<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'email'        => $this->email,
            'status'       => $this->status,
            'project_id'   => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project->name),
            'inviter_name' => $this->whenLoaded('inviter', fn () => $this->inviter->name),
            'expires_at'   => $this->expires_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
