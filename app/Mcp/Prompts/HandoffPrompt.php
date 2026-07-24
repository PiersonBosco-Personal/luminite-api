<?php

namespace App\Mcp\Prompts;

class HandoffPrompt extends Prompt
{
    public function definition(): array
    {
        return [
            'name'        => 'handoff',
            'description' => 'Produce a handoff snapshot for the other developer — current state, what is in flight and why, the active decisions, and recommended next steps.',
        ];
    }

    public function messages(): array
    {
        $text = <<<'PROMPT'
        Write a handoff snapshot for the other developer so they can pick this project up cold. Gather the state first:

        1. Call get_thread for the recent project memory stream (where work was left off, gotchas, dead-ends).
        2. Call get_decisions for the active decisions and their rationale.
        3. Call get_open_tasks for what is still open and in progress.

        Then write a concise plain-prose summary a teammate can read cold: the current state, what is in flight and why, the active decisions that matter, and the recommended next steps. No numeric ids — refer to tasks and decisions by name.
        PROMPT;

        return [[
            'role'    => 'user',
            'content' => ['type' => 'text', 'text' => $text],
        ]];
    }
}
