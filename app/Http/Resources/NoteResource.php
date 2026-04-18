<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'project_id' => $this->project_id,
            'folder_id'  => $this->folder_id,
            'task_id'    => $this->task_id,
            'author'     => new UserResource($this->whenLoaded('author')),
            'title'      => $this->title,
            'content'    => $this->content,
            'is_pinned'  => $this->is_pinned,
            'position'   => (float) $this->position,
            'labels'     => LabelResource::collection($this->whenLoaded('labels')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
        ];
    }
}
