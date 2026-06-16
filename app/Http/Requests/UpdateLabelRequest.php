<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $label   = $this->route('label');

        return [
            'name'  => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('labels', 'name')
                    ->where('project_id', $project->id)
                    ->ignore($label->id),
            ],
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A label with this name already exists in this project.',
        ];
    }
}
