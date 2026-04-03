<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id'   => 'sometimes|nullable|integer|exists:tasks,id',
            'title'     => 'sometimes|string|max:255',
            'content'   => 'sometimes|nullable',
            'is_pinned' => 'sometimes|boolean',
        ];
    }
}
