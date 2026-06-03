<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->route('project')->id;

        return [
            'task_id'          => ['required', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
            'work_type_id'     => ['nullable', 'integer', Rule::exists('work_types', 'id')->where('project_id', $projectId)],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'logged_at'        => ['nullable', 'date'],
        ];
    }
}
