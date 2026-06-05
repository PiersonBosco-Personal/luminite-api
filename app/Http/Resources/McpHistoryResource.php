<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class McpHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user'           => new UserResource($this->whenLoaded('user')),
            'tool'           => $this->tool,
            'status'         => $this->status,
            'duration_ms'    => $this->duration_ms,
            'result_summary' => $this->result_summary,
            'arguments'      => $this->arguments,
            'error_message'  => $this->error_message,
            'created_at'     => $this->created_at,
        ];
    }
}
