<?php

namespace App\Mcp\Tools;

use App\Events\SectionCreated;
use App\Events\SectionsReordered;
use App\Events\SectionUpdated;
use App\Models\TaskSection;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageSection extends Tool
{
    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'manage_section',
            'description' => 'Create, rename, or reorder task sections (board columns). action="create" needs name; action="rename" needs section_id + name; action="reorder" needs order (an array of section ids in the desired left-to-right order). The #id/[id] shown is for your tool calls only — never repeat it to the user; refer to items by title. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'action'     => ['type' => 'string', 'enum' => ['create', 'rename', 'reorder']],
                    'name'       => ['type' => 'string'],
                    'section_id' => ['type' => 'integer'],
                    'order'      => ['type' => 'array', 'items' => ['type' => 'integer']],
                ],
                'required' => ['action'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);
        $action    = $args['action'] ?? '';

        return match ($action) {
            'create'  => $this->create($projectId, $userId, $args),
            'rename'  => $this->rename($projectId, $userId, $args),
            'reorder' => $this->reorder($projectId, $userId, $args),
            default   => 'Error: action must be one of create, rename, reorder.',
        };
    }

    private function create(int $projectId, int $userId, array $args): string
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return 'Error: name is required to create a section.';
        }

        $position = (int) TaskSection::where('project_id', $projectId)->max('position') + 1;
        $section  = TaskSection::create(['project_id' => $projectId, 'name' => $name, 'position' => $position]);

        broadcast(new SectionCreated($section, $projectId));
        $this->log($projectId, $userId, 'section.created', $section->name, $section->id, "created section {$section->name}");

        return "Created section #{$section->id}: {$section->name}";
    }

    private function rename(int $projectId, int $userId, array $args): string
    {
        $id   = (int) ($args['section_id'] ?? 0);
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return 'Error: name is required to rename a section.';
        }

        $section = TaskSection::where('project_id', $projectId)->whereKey($id)->first();
        if (! $section) {
            return "Error: section #{$id} not found in this project.";
        }

        $section->update(['name' => $name]);
        broadcast(new SectionUpdated($section, $projectId));
        $this->log($projectId, $userId, 'section.updated', $section->name, $section->id, "renamed section to {$section->name}");

        return "Renamed section #{$section->id} to {$section->name}";
    }

    private function reorder(int $projectId, int $userId, array $args): string
    {
        $order = array_map('intval', (array) ($args['order'] ?? []));
        if ($order === []) {
            return 'Error: order must be a non-empty array of section ids.';
        }

        $valid = TaskSection::where('project_id', $projectId)->pluck('id')->all();
        foreach ($order as $id) {
            if (! in_array($id, $valid, true)) {
                return "Error: section #{$id} is not in this project.";
            }
        }

        DB::transaction(function () use ($order, $projectId) {
            foreach ($order as $position => $id) {
                TaskSection::where('project_id', $projectId)->whereKey($id)->update(['position' => $position]);
            }
        });

        broadcast(new SectionsReordered($projectId));
        $this->log($projectId, $userId, 'section.reordered', 'sections', null, 'reordered sections');

        return 'Reordered ' . count($order) . ' sections.';
    }

    private function log(int $projectId, int $userId, string $event, string $label, ?int $subjectId, string $description): void
    {
        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    $event,
            subjectType:  'section',
            subjectLabel: $label,
            subjectId:    $subjectId,
            description:  $description,
            viaMcp:       true,
        );
    }
}
