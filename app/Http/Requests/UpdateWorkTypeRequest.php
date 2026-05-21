<?php

namespace App\Http\Requests;

use App\Models\WorkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'sometimes|required|string|max:100',
            'is_active' => 'sometimes|boolean',
            'color'     => ['sometimes', 'nullable', Rule::in(WorkType::PALETTE)],
        ];
    }
}
