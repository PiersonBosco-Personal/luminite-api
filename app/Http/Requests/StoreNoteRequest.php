<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id'   => 'nullable|integer|exists:tasks,id',
            'title'     => 'required|string|max:255',
            'content'   => 'nullable',
            'is_pinned' => 'sometimes|boolean',
        ];
    }
}
