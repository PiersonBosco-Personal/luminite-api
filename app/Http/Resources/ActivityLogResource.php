<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'project_id'    => $this->project_id,
            'user'          => new UserResource($this->whenLoaded('user')),
            'event_type'    => $this->event_type,
            'subject_type'  => $this->subject_type,
            'subject_id'    => $this->subject_id,
            'subject_label' => $this->subject_label,
            'description'   => $this->description,
            'old_value'     => $this->old_value,
            'new_value'     => $this->new_value,
            'field_changed' => $this->field_changed,
            'via_mcp'       => $this->via_mcp,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
