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
            'layout'     => 'required|array',
            'layout.*.i' => 'required|string',
            'layout.*.x' => 'required|integer|min:0',
            'layout.*.y' => 'required|integer|min:0',
            'layout.*.w' => 'required|integer|min:1',
            'layout.*.h' => 'required|integer|min:1',
        ];
    }
}
