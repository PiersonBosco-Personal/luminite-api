<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id'          => 'required|integer|exists:tasks,id',
            'work_type_id'     => 'nullable|integer|exists:work_types,id',
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'description'      => 'nullable|string|max:2000',
            'logged_at'        => 'nullable|date',
        ];
    }
}
