<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'project_id'       => $this->project_id,
            'task_id'          => $this->task_id,
            'task'             => $this->whenLoaded('task', fn () => $this->task ? [
                'id'    => $this->task->id,
                'title' => $this->task->title,
            ] : null),
            'user_id'          => $this->user_id,
            'user'             => new UserResource($this->whenLoaded('user')),
            'work_type_id'     => $this->work_type_id,
            'work_type'        => new WorkTypeResource($this->whenLoaded('workType')),
            'description'      => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'started_at'       => $this->started_at,
            'stopped_at'       => $this->stopped_at,
            'logged_at'        => $this->logged_at?->toDateString(),
            'is_running'       => $this->isRunning(),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
