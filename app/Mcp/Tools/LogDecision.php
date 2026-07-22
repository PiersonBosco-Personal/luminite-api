<?php

namespace App\Mcp\Tools;

use App\Jobs\EmbedRecord;
use App\Models\Decision;
use App\Models\Task;
use App\Models\ThreadEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogDecision extends Tool
{
    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'log_decision',
            'description' => 'Record a project decision as durable, first-class truth (NOT a plain thread entry). Call this whenever you settle a choice — a library, an approach, a convention, a tradeoff — and capture the WHY in rationale. If this decision replaces an earlier one, pass supersedes with that decision\'s id: the old one is marked superseded and linked, so "what is our current X?" always has exactly one answer while the history stays queryable. Active decisions are injected at the start of every session. Optionally breadcrumb the originating task with task_id. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'decision'   => ['type' => 'string', 'description' => 'What was decided, one line. E.g. "Use Square as the payment processor".'],
                    'rationale'  => ['type' => 'string', 'description' => 'The why — the reasoning behind the decision.'],
                    'supersedes' => ['type' => 'integer', 'description' => 'Optional id of the active decision this one replaces.'],
                    'task_id'    => ['type' => 'integer', 'description' => 'Optional task this decision relates to.'],
                ],
                'required' => ['decision', 'rationale'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);

        $decision = trim((string) ($args['decision'] ?? ''));
        if ($decision === '') {
            return 'Error: decision is required.';
        }

        $rationale = trim((string) ($args['rationale'] ?? ''));
        if ($rationale === '') {
            return 'Error: rationale is required — capture the why.';
        }

        $taskId = null;
        if (isset($args['task_id']) && $args['task_id'] !== '') {
            $taskId = (int) $args['task_id'];
            if (! Task::where('project_id', $projectId)->whereKey($taskId)->exists()) {
                return "Error: task #{$taskId} not found in this project.";
            }
        }

        $supersedes = null;
        if (isset($args['supersedes']) && $args['supersedes'] !== '') {
            $supersedesId = (int) $args['supersedes'];
            $supersedes   = Decision::where('project_id', $projectId)->whereKey($supersedesId)->first();
            if (! $supersedes) {
                return "Error: decision #{$supersedesId} not found in this project.";
            }
            if ($supersedes->status !== 'active') {
                return "Error: decision #{$supersedes->id} is already superseded.";
            }
        }

        $new = DB::transaction(function () use ($projectId, $userId, $taskId, $decision, $rationale, $supersedes) {
            $created = Decision::create([
                'project_id' => $projectId,
                'task_id'    => $taskId,
                'created_by' => $userId,
                'decision'   => $decision,
                'rationale'  => $rationale,
                'status'     => 'active',
            ]);

            if ($supersedes) {
                $supersedes->update([
                    'status'           => 'superseded',
                    'superseded_by_id' => $created->id,
                ]);
            }

            // Chronological breadcrumb into the Thread so the stream still reads
            // as a narrative. The decisions table is the truth; this is the trail.
            ThreadEntry::create([
                'project_id' => $projectId,
                'task_id'    => $taskId,
                'created_by' => $userId,
                'type'       => 'momentum',
                'trigger'    => 'manual',
                'content'    => 'Decided: ' . $decision . ($supersedes ? " (supersedes #{$supersedes->id})" : ''),
            ]);

            return $created;
        });

        // Deliberately no ActivityLogService::log() and no broadcast (spec §4.1):
        // the decision log is its own channel; mcp_history is the audit trail.
        $note = $supersedes ? " (superseded #{$supersedes->id})" : '';

        // Index the decision for semantic recall, off the critical path (spec §5.3).
        EmbedRecord::dispatch('decision', $new->id);

        return "Logged decision #{$new->id}: {$decision}{$note}.";
    }
}
