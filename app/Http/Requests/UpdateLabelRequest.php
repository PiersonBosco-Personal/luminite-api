<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => 'sometimes|string|max:255',
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }
}
