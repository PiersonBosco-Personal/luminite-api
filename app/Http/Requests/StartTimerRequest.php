<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id'      => 'required|integer|exists:tasks,id',
            'work_type_id' => 'nullable|integer|exists:work_types,id',
            'description'  => 'nullable|string|max:2000',
        ];
    }
}
