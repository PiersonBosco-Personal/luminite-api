<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCompletionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'task'         => [
                'id'    => $this->task_id,
                'title' => $this->whenLoaded('task', fn () => $this->task->title),
            ],
            'completed_by' => [
                'id'   => $this->completed_by_user_id,
                'name' => $this->whenLoaded('completedBy', fn () => $this->completedBy?->name),
            ],
            'summary_what' => $this->summary_what,
            'summary_why'  => $this->summary_why,
            'source'       => $this->source,
            'created_at'   => $this->created_at,
        ];
    }
}
