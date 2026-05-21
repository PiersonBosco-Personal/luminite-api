<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'project_id'         => $this->project_id,
            'name'               => $this->name,
            'is_active'          => $this->is_active,
            'color'              => $this->color,
            'time_entries_count' => $this->time_entries_count ?? 0,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
