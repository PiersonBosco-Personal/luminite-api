<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->route('project')->id;

        return [
            'task_id'          => ['sometimes', 'nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
            'work_type_id'     => ['sometimes', 'nullable', 'integer', Rule::exists('work_types', 'id')->where('project_id', $projectId)],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'description'      => ['sometimes', 'nullable', 'string', 'max:2000'],
            'logged_at'        => ['sometimes', 'date'],
        ];
    }
}
