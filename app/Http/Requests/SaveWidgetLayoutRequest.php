<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWidgetLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'layout'         => 'required|array',
            'layout.*.id'    => 'required|integer|exists:dashboard_widgets,id',
            'layout.*.grid_x' => 'required|integer|min:0',
            'layout.*.grid_y' => 'required|integer|min:0',
            'layout.*.grid_w' => 'required|integer|min:1',
            'layout.*.grid_h' => 'required|integer|min:1',
        ];
    }
}
