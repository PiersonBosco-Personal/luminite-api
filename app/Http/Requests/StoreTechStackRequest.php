<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTechStackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'version'   => 'nullable|string|max:50',
            'parent_id' => 'nullable|integer|exists:tech_stacks,id',
        ];
    }
}
