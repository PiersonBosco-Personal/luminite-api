<?php

namespace App\Mcp\Prompts;

class ResumePrompt extends Prompt
{
    public function definition(): array
    {
        return [
            'name'        => 'resume',
            'description' => 'Start of session: re-orient from project memory — read the Thread and active decisions, summarize where work was left off, and propose the next step.',
        ];
    }

    public function messages(): array
    {
        $text = <<<'PROMPT'
        Re-orient me at the start of this session using Luminite's project memory:

        1. Call get_thread to read the recent project memory stream (where work was left off, recent gotchas and dead-ends).
        2. Call get_decisions to read the active decisions (the current truth).
        3. Summarize in a few lines: where we left off, what is in flight, and any decision that constrains the next move.
        4. Propose the single next concrete step.

        Do not start changing code until I confirm the direction.
        PROMPT;

        return [[
            'role'    => 'user',
            'content' => ['type' => 'text', 'text' => $text],
        ]];
    }
}
