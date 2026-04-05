<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'name'        => $this->name,
            'category'    => $this->category,
            'description' => $this->description,
            'icon'        => $this->icon,
            'is_active'   => $this->is_active,
            'default_w'   => $this->default_w,
            'default_h'   => $this->default_h,
            'min_w'       => $this->min_w,
            'min_h'       => $this->min_h,
        ];
    }
}
