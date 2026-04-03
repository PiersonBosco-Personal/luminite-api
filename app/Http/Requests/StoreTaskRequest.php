<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id'     => 'required|integer|exists:task_sections,id',
            'parent_task_id' => 'nullable|integer|exists:tasks,id',
            'assigned_to'    => 'nullable|integer|exists:users,id',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'status'         => 'sometimes|in:todo,in_progress,done,blocked',
            'priority'       => 'sometimes|in:low,medium,high,urgent',
            'due_date'       => 'nullable|date',
            'position'       => 'sometimes|integer|min:0',
        ];
    }
}
