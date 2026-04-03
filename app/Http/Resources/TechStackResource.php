<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechStackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'project_id' => $this->project_id,
            'name'       => $this->name,
            'app_label'  => $this->app_label,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
