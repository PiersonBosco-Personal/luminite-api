<?php

namespace App\Mcp\Prompts;

class InitializeProjectPrompt extends Prompt
{
    private const TEXT = <<<'PROMPT'
You are initializing a Luminite project. Follow these steps exactly, in order.

1. Call get_session_context. If the project already has any of: a description, goals,
   architecture notes, tech stack entries, sections, labels, or tasks — STOP. Tell the
   user this project is already initialized; initialize_project only works on a blank
   project.

2. Scan the repository: README, package.json / composer.json / pyproject.toml or
   equivalents, the folder structure, and any existing docs. From evidence only, draft:
   - description — what the project is
   - architecture_notes — how it is structured
   - tech_stack — frameworks, languages, services; one level of nesting
     (e.g. Laravel with a Reverb child)

3. Interview the user for what the scan cannot reveal. Ask only about the gaps:
   project goals, current status, what the first tasks should be, and their priorities.
   If the repo is empty or greenfield, interview for everything.

4. Build the complete draft within these server-enforced limits:
   - details: description (required, 1-5000 chars), goals and architecture_notes
     (each up to 5000 chars)
   - tech_stack: max 30 entries counting parents and children; name up to 100 chars,
     version up to 50
   - sections: max 6 names (array order = board order), e.g. Backlog / In Progress / Done
   - labels: max 10 of {name (up to 50 chars), color}; color is any hex like #c0392b
     — pick distinguishable colors that suit a dark blue UI
   - tasks: max 25 of {title (required, up to 200 chars), description (up to 2000),
     priority (low|medium|high|urgent, default medium), section, labels};
     IMPORTANT: section and labels are integer INDEXES into the sections[] and labels[]
     arrays of this same payload — not ids, not names
   - widgets: max 6 catalog slugs from: tasks_board, notes_list, activity_feed, ai_chat,
     task_burndown, deadline_tracker, label_breakdown, time_tracker, time_report

5. Show the complete draft in chat — details text, tech-stack tree, sections, labels,
   tasks, widget set — and get the user's explicit approval. Do not call the tool
   before approval. Revise and re-show if they ask for changes.

6. After approval, call initialize_project exactly once with the approved payload.
   The server validates everything and applies it atomically. If it returns an error,
   fix the payload and retry.

7. Report the result and tell the user to open the project in Luminite to see the
   Details page, taskboard, and dashboard widgets.
PROMPT;

    public function definition(): array
    {
        return [
            'name'        => 'initialize-project',
            'description' => 'Jump-start a blank Luminite project: scan the codebase, interview the user for gaps, then write the project details, taskboard, and starter dashboard in one atomic call.',
        ];
    }

    public function messages(): array
    {
        return [
            [
                'role'    => 'user',
                'content' => ['type' => 'text', 'text' => self::TEXT],
            ],
        ];
    }
}
