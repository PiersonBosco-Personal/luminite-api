<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\ThreadEntry;
use Illuminate\Http\Request;

class AddThreadEntry extends Tool
{
    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'add_thread_entry',
            'description' => 'Append an entry to the project memory ("the Thread"). Call this the moment you make a decision, rule out a dead-end, hit a gotcha, or park work — capture WHY, not just what. This project-scoped memory is injected at the start of future sessions so you and your teammate resume instantly. type is one of: momentum (where you left off / the next move), decision, dead_end, gotcha. Optionally pass task_id to breadcrumb which task it came from. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'type'    => ['type' => 'string', 'enum' => ThreadEntry::TYPES],
                    'content' => ['type' => 'string', 'description' => 'The entry body — the why/what, plain text.'],
                    'task_id' => ['type' => 'integer', 'description' => 'Optional task this entry relates to.'],
                ],
                'required' => ['type', 'content'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);

        $type = (string) ($args['type'] ?? '');
        if (! in_array($type, ThreadEntry::TYPES, true)) {
            $allowed = implode(', ', ThreadEntry::TYPES);
            return "Error: type must be one of: {$allowed}.";
        }

        $content = trim((string) ($args['content'] ?? ''));
        if ($content === '') {
            return 'Error: content is required.';
        }

        $taskId = null;
        if (isset($args['task_id']) && $args['task_id'] !== '') {
            $taskId = (int) $args['task_id'];
            if (! Task::where('project_id', $projectId)->whereKey($taskId)->exists()) {
                return "Error: task #{$taskId} not found in this project.";
            }
        }

        ThreadEntry::create([
            'project_id' => $projectId,
            'task_id'    => $taskId,
            'created_by' => $userId,
            'type'       => $type,
            'content'    => $content,
            'trigger'    => 'manual',
        ]);

        // Deliberately no ActivityLogService::log() and no broadcast (spec §3):
        // the Thread is its own channel; mcp_history records the call centrally.
        $linked = $taskId ? " (task #{$taskId})" : '';

        return "Recorded {$type} entry to project memory{$linked}.";
    }
}
