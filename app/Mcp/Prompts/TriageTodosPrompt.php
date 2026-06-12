<?php

namespace App\Mcp\Prompts;

class TriageTodosPrompt extends Prompt
{
    public function definition(): array
    {
        return [
            'name'        => 'triage-todos',
            'description' => 'Clean up the Triage section: re-parent code-synced TODOs under the right tasks, set priorities, and remove duplicates.',
        ];
    }

    public function messages(): array
    {
        $text = <<<'PROMPT'
        You are triaging the Triage section of this Luminite project — tasks auto-created from code TODO/FIXME comments.

        Do this:
        1. Call get_sections to find the "Triage" section id, then get_open_tasks with that section_id to list the untriaged todos.
        2. Call get_open_tasks (no filter) to see the project's real tasks for context.
        3. For each Triage todo, decide:
           - Is it a subtask of an existing task? If so, call update_task with parent set to that task's id (and move it into a working section).
           - Is it standalone work? Set an appropriate priority and section with update_task.
           - Is it a duplicate of existing work? Call complete_task to close it.
        4. Summarize what you triaged for the user before making changes, then apply them.

        Work in small batches and keep the user informed. Do not invent tasks that are not represented by a todo.
        PROMPT;

        return [[
            'role'    => 'user',
            'content' => ['type' => 'text', 'text' => $text],
        ]];
    }
}
