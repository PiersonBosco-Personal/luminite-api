<?php

namespace App\Mcp\Prompts;

class WrapUpPrompt extends Prompt
{
    public function definition(): array
    {
        return [
            'name'        => 'wrap-up',
            'description' => 'End-of-session: reconcile task states, write a session note, and log the session to Luminite.',
        ];
    }

    public function messages(): array
    {
        $text = <<<'PROMPT'
        Wrap up this coding session in Luminite. Work through these steps:

        1. Review what changed in this session (the files you edited and what you accomplished).
        2. For the task you were working on: if it is finished, call complete_task; if it advanced but is not done, call update_task to move it to In Progress and update its description with current state.
        3. For anything worth remembering next session — a decision made, a dead-end ruled out, a gotcha, or where you left off and what's next — call add_thread_entry with the right type (decision / dead_end / gotcha / momentum) and the WHY. Breadcrumb the originating task with task_id when there is one.
        4. Call log_session_activity with a one-line summary, the files changed, and the count of tasks completed.

        Confirm the task you believe you were working on with the user if it is ambiguous. Keep changes faithful to what actually happened.
        PROMPT;

        return [[
            'role'    => 'user',
            'content' => ['type' => 'text', 'text' => $text],
        ]];
    }
}
