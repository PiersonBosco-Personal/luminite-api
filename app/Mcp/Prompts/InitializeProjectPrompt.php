<?php

namespace App\Mcp\Prompts;

class InitializeProjectPrompt extends Prompt
{
    private const TEXT = <<<'PROMPT'
You are initializing a Luminite project. Your MCP token is bound to ONE specific,
already-existing project — the one this token belongs to. You cannot create, switch to,
or reach any other project. NEVER offer to "make a new project" or a "blank project" —
that is impossible here. The only project you can act on is this one.

Follow these steps exactly, in order.

1. Call get_session_context to read the project's current state.
   - If it is blank (no description, goals, architecture notes, tech stack, sections,
     labels, or tasks), continue to step 2 for a first-time initialization.
   - If it ALREADY has any of those, it is initialized. Do NOT suggest creating another
     project — you can't. The only path forward is a destructive OVERWRITE
     (initialize_project with confirm: true), which permanently deletes the existing
     details, tech stack, sections, tasks, labels, and the user's dashboard widgets and
     replaces them with a fresh payload (notes and folders are kept, though notes lose
     any label tags). Offer this only if the user genuinely wants to start the project
     over; if not, leave everything untouched.

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
   - sections: max 6 names (array order = board order), e.g. Backlog / In Progress / Done.
     Do NOT add a "Triage", "Inbox", or any holding/unsorted section — there is no inbox
     concept. A task created without a section automatically lands in the first section.
     Only propose sections that represent real workflow stages.
   - labels: max 10 of {name (up to 50 chars), color}; color is any hex like #c0392b
     — pick distinguishable colors that suit a dark blue UI
   - tasks: max 25 of {title (required, up to 200 chars), description (up to 2000),
     priority (low|medium|high|urgent, default medium), section, labels}. Keep the
     description for context — break work into steps later with create_task subtasks,
     not as a step list inside description.
     IMPORTANT: section and labels are integer INDEXES into the sections[] and labels[]
     arrays of this same payload — not ids, not names
   - widgets: max 6 catalog slugs, ONLY from the built set:
     tasks_board, notes_list, activity_feed, deadline_tracker, time_tracker, time_report.
     The server rejects any other slug.

5. Show the user the COMPLETE draft, then get their explicit approval.
   CRITICAL — how to show it: print the entire draft as plain, readable markdown
   directly in your reply body, BEFORE you ask anything. Include every part in full:
   the description / goals / architecture-notes text, the tech-stack tree, the section
   list, every label (name + color), every task (title, section, priority, labels,
   description), and the widget set. Nothing abbreviated, nothing collapsed.
   Do NOT place the draft inside an approval prompt, a confirmation dialog, a tool
   "preview", or any UI widget — those truncate long content and the user will approve
   blind. The full draft must be visible as ordinary message text above your question.
   Only after the draft is fully printed, ask the user to approve or request changes.
   Do not call the tool before they approve. Revise and re-print the full draft if they
   ask for changes.

   FOR AN OVERWRITE, this approval step is mandatory and stricter, because it destroys
   existing data. Re-print the full draft, and directly above your question state plainly
   what will be destroyed using the real current counts from get_session_context — e.g.
   "This permanently deletes the existing N tasks, N labels, N sections, N tech-stack
   entries and your dashboard widgets, then replaces them with the draft above. This
   cannot be undone." A menu pick, a "2", or a "try again" is NOT approval of the payload
   — wait for an explicit yes to THIS draft before proceeding.

6. After explicit approval, call initialize_project exactly once with the approved
   payload — add confirm: true ONLY for an overwrite. The server validates everything and
   applies it atomically. If it returns an error, fix the payload and retry (re-confirm
   with the user first if the content changed).

7. Report the result — including the removed/created counts the tool returns on an
   overwrite — and tell the user to open the project in Luminite to see the Details page,
   taskboard, and dashboard widgets.
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
