<?php

namespace App\Mcp\Prompts;

class TriageTodosPrompt extends Prompt
{
    public function definition(): array
    {
        return [
            'name' => 'triage-todos',
            'description' => 'Route the Triage inbox: move newly-created, unsorted tasks out of the Triage section into the right working sections with appropriate priority.',
        ];
    }

    public function messages(): array
    {
        $text = <<<'PROMPT'
        You are routing the Triage inbox of this Luminite project — the holding column where newly-created, unsorted tasks land.

        Do this:
        1. Call get_sections to find the "Triage" section id, then get_open_tasks with that section_id to list the unsorted tasks.
        2. Call get_open_tasks (no filter) to see the project's real sections and existing work for context.
        3. For each task in Triage, decide where it belongs:
           - Standalone work → call update_task with an appropriate section and priority.
           - A subtask of existing work → call update_task with parent set to that task's id, and move it into a working section.
           - A duplicate of existing work → call complete_task to close it.
        4. Summarize what you plan to do for the user before making changes, then apply them in small batches.

        Refer to tasks by name, not by id. Do not invent tasks.
        PROMPT;

        return [[
            'role' => 'user',
            'content' => ['type' => 'text', 'text' => $text],
        ]];
    }
}
