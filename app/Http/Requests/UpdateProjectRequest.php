<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => 'sometimes|string|max:255',
            'description'        => 'sometimes|nullable|string',
            'status'             => 'sometimes|in:active,archived',
            'goals'              => 'sometimes|nullable|string',
            'architecture_notes' => 'sometimes|nullable|string',
        ];
    }
}
