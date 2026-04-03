<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id'     => 'sometimes|integer|exists:task_sections,id',
            'parent_task_id' => 'sometimes|nullable|integer|exists:tasks,id',
            'assigned_to'    => 'sometimes|nullable|integer|exists:users,id',
            'title'          => 'sometimes|string|max:255',
            'description'    => 'sometimes|nullable|string',
            'status'         => 'sometimes|in:todo,in_progress,done,blocked',
            'priority'       => 'sometimes|in:low,medium,high,urgent',
            'due_date'       => 'sometimes|nullable|date',
            'position'       => 'sometimes|integer|min:0',
        ];
    }
}
