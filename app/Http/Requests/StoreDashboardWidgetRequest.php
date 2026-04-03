<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => 'required|string|max:255',
            'config' => 'nullable|array',
            'grid_x' => 'sometimes|integer|min:0',
            'grid_y' => 'sometimes|integer|min:0',
            'grid_w' => 'sometimes|integer|min:1',
            'grid_h' => 'sometimes|integer|min:1',
        ];
    }
}
