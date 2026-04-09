<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'description'        => $this->description,
            'goals'              => $this->goals,
            'architecture_notes' => $this->architecture_notes,
            'status'             => $this->status,
            'owner'              => new UserResource($this->whenLoaded('owner')),
            'members'            => UserResource::collection($this->whenLoaded('members')),
            'members_count'      => $this->whenCounted('members'),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
