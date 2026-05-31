<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id'          => 'sometimes|nullable|integer|exists:tasks,id',
            'work_type_id'     => 'sometimes|nullable|integer|exists:work_types,id',
            'duration_minutes' => 'sometimes|integer|min:1|max:1440',
            'description'      => 'sometimes|nullable|string|max:2000',
            'logged_at'        => 'sometimes|date',
        ];
    }
}
