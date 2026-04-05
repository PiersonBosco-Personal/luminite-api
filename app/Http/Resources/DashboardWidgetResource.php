<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardWidgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'project_id' => $this->project_id,
            'user_id'    => $this->user_id,
            'widget_id'  => $this->widget_id,
            'widget'     => new WidgetResource($this->whenLoaded('widget')),
            'grid_x'     => $this->grid_x,
            'grid_y'     => $this->grid_y,
            'grid_w'     => $this->grid_w,
            'grid_h'     => $this->grid_h,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
