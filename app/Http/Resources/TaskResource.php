<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'project_id'     => $this->project_id,
            'section_id'     => $this->section_id,
            'parent_task_id' => $this->parent_task_id,
            'assignee'       => new UserResource($this->whenLoaded('assignee')),
            'title'          => $this->title,
            'description'    => $this->description,
            'status'         => $this->status,
            'priority'       => $this->priority,
            'due_date'       => $this->due_date?->toDateString(),
            'position'       => $this->position,
            'labels'         => LabelResource::collection($this->whenLoaded('labels')),
            'subtasks'       => TaskResource::collection($this->whenLoaded('subtasks')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
