<?php

namespace App\Mcp\Tools;

use App\Events\ProjectInitialized;
use App\Mcp\Validation\InitializeProjectPayload;
use App\Models\DashboardWidget;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskSection;
use App\Models\TechStack;
use App\Models\Widget;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InitializeProject extends Tool
{
    public function requiredScope(): string
    {
        return 'write';
    }

    public function definition(): array
    {
        return [
            'name'        => 'initialize_project',
            'description' => 'One-shot initialization of a BLANK project: sets the Details page (description, goals, architecture notes), tech stack, board sections, labels, tasks, and the calling user\'s starter dashboard widgets — atomically. Refuses if the project already has any of those (existing widgets do not block). task.section and task.labels are integer INDEXES into the sections/labels arrays of this same payload. Requires a token with the write scope.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'details' => [
                        'type'       => 'object',
                        'properties' => [
                            'description'        => ['type' => 'string', 'maxLength' => 5000],
                            'goals'              => ['type' => 'string', 'maxLength' => 5000],
                            'architecture_notes' => ['type' => 'string', 'maxLength' => 5000],
                        ],
                        'required'             => ['description'],
                        'additionalProperties' => false,
                    ],
                    'tech_stack' => [
                        'type'        => 'array',
                        'description' => 'Max 30 entries total (parents + children).',
                        'items'       => [
                            'type'       => 'object',
                            'properties' => [
                                'name'     => ['type' => 'string', 'maxLength' => 100],
                                'version'  => ['type' => 'string', 'maxLength' => 50],
                                'children' => [
                                    'type'  => 'array',
                                    'items' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'name'    => ['type' => 'string', 'maxLength' => 100],
                                            'version' => ['type' => 'string', 'maxLength' => 50],
                                        ],
                                        'required'             => ['name'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required'             => ['name'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'sections' => [
                        'type'        => 'array',
                        'maxItems'    => 6,
                        'description' => 'Board sections; array order = board order.',
                        'items'       => ['type' => 'string', 'maxLength' => 100],
                    ],
                    'labels' => [
                        'type'     => 'array',
                        'maxItems' => 10,
                        'items'    => [
                            'type'       => 'object',
                            'properties' => [
                                'name'  => ['type' => 'string', 'maxLength' => 50],
                                'color' => ['type' => 'string', 'pattern' => '^#[0-9a-fA-F]{6}$', 'description' => 'Any #RRGGBB hex.'],
                            ],
                            'required'             => ['name', 'color'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'tasks' => [
                        'type'     => 'array',
                        'maxItems' => 25,
                        'items'    => [
                            'type'       => 'object',
                            'properties' => [
                                'title'       => ['type' => 'string', 'maxLength' => 200],
                                'description' => ['type' => 'string', 'maxLength' => 2000],
                                'priority'    => ['type' => 'string', 'enum' => InitializeProjectPayload::PRIORITIES],
                                'section'     => ['type' => 'integer', 'description' => 'Index into sections[] of this payload.'],
                                'labels'      => [
                                    'type'        => 'array',
                                    'description' => 'Indexes into labels[] of this payload.',
                                    'items'       => ['type' => 'integer'],
                                ],
                            ],
                            'required'             => ['title', 'section'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'widgets' => [
                        'type'        => 'array',
                        'maxItems'    => 6,
                        'description' => 'Widget catalog slugs; validated against the widgets table.',
                        'items'       => ['type' => 'string'],
                    ],
                ],
                'required'             => ['details'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);
        $userId    = $this->userId($request);

        $project = Project::find($projectId);
        if (! $project) {
            return 'Error: the project associated with this token no longer exists.';
        }

        $validSlugs = Widget::where('is_active', true)->pluck('slug')->all();

        $result = DB::transaction(function () use ($args, $project, $projectId, $userId, $validSlugs) {
            // 1. Blank-project guard — first, inside the transaction.
            //    Re-read under lock: a plain snapshot read could let two
            //    concurrent initializers both see a blank project.
            //    Widgets deliberately do NOT block: they are per-user.
            $project = Project::whereKey($projectId)->lockForUpdate()->first();
            if (! $project) {
                return ['error' => 'Error: the project associated with this token no longer exists.'];
            }

            $notBlank = filled($project->description)
                || filled($project->goals)
                || filled($project->architecture_notes)
                || TechStack::where('project_id', $projectId)->exists()
                || TaskSection::where('project_id', $projectId)->exists()
                || Label::where('project_id', $projectId)->exists()
                || Task::where('project_id', $projectId)->exists();

            if ($notBlank) {
                return ['error' => 'Error: project already initialized.'];
            }

            // 2. Validate — every cap, enum, index range, unknown-key rule.
            try {
                $payload = (new InitializeProjectPayload())->validate($args, $validSlugs);
            } catch (ToolInputException $e) {
                return ['error' => 'Error: ' . $e->getMessage()];
            }

            // 3a. Project details
            $project->update([
                'description'        => $payload['details']['description'],
                'goals'              => $payload['details']['goals'] !== '' ? $payload['details']['goals'] : null,
                'architecture_notes' => $payload['details']['architecture_notes'] !== '' ? $payload['details']['architecture_notes'] : null,
            ]);

            // 3b. Tech stack — parents, then children
            $stackCount = 0;
            foreach ($payload['tech_stack'] as $entry) {
                $parent = TechStack::create([
                    'project_id' => $projectId,
                    'name'       => $entry['name'],
                    'version'    => $entry['version'],
                ]);
                $stackCount++;

                foreach ($entry['children'] as $child) {
                    TechStack::create([
                        'project_id' => $projectId,
                        'parent_id'  => $parent->id,
                        'name'       => $child['name'],
                        'version'    => $child['version'],
                    ]);
                    $stackCount++;
                }
            }

            // 3c. Sections — position = array order
            $sectionIds = [];
            foreach ($payload['sections'] as $position => $name) {
                $sectionIds[] = TaskSection::create([
                    'project_id' => $projectId,
                    'name'       => $name,
                    'position'   => $position,
                ])->id;
            }

            // 3d. Labels
            $labelIds = [];
            foreach ($payload['labels'] as $label) {
                $labelIds[] = Label::create([
                    'project_id' => $projectId,
                    'name'       => $label['name'],
                    'color'      => $label['color'],
                ])->id;
            }

            // 3e. Tasks — position within each section = payload order
            $taskCount        = 0;
            $sectionPositions = [];
            foreach ($payload['tasks'] as $task) {
                $position = $sectionPositions[$task['section']] ?? 0;
                $sectionPositions[$task['section']] = $position + 1;

                $model = Task::create([
                    'project_id'  => $projectId,
                    'section_id'  => $sectionIds[$task['section']],
                    'created_by'  => $userId,
                    'title'       => $task['title'],
                    'description' => $task['description'],
                    'status'      => 'todo',
                    'priority'    => $task['priority'],
                    'position'    => $position,
                ]);

                if ($task['labels']) {
                    $model->labels()->sync(array_map(fn (int $i) => $labelIds[$i], $task['labels']));
                }

                $taskCount++;
            }

            // 3f. Widgets — token user's dashboard, default grid sizes,
            //     stacked below existing widgets; already-placed slugs skipped.
            $placed = 0;
            if ($payload['widgets']) {
                $catalog = Widget::whereIn('slug', $payload['widgets'])->get()->keyBy('slug');

                $existingSlugs = DashboardWidget::where('project_id', $projectId)
                    ->where('user_id', $userId)
                    ->with('widget')
                    ->get()
                    ->pluck('widget.slug')
                    ->all();

                $maxY = DashboardWidget::where('project_id', $projectId)
                    ->where('user_id', $userId)
                    ->selectRaw('MAX(grid_y + grid_h) as max_y')
                    ->value('max_y') ?? 0;

                foreach ($payload['widgets'] as $slug) {
                    if (in_array($slug, $existingSlugs, true)) {
                        continue;
                    }

                    $widget = $catalog[$slug];

                    DashboardWidget::create([
                        'project_id' => $projectId,
                        'user_id'    => $userId,
                        'widget_id'  => $widget->id,
                        'grid_x'     => 0,
                        'grid_y'     => $maxY,
                        'grid_w'     => $widget->default_w,
                        'grid_h'     => $widget->default_h,
                    ]);

                    $maxY += $widget->default_h;
                    $placed++;
                }
            }

            return [
                'counts' => [
                    'stack'    => $stackCount,
                    'sections' => count($sectionIds),
                    'labels'   => count($labelIds),
                    'tasks'    => $taskCount,
                    'widgets'  => $placed,
                ],
            ];
        });

        if (isset($result['error'])) {
            return $result['error'];
        }

        // 4. Broadcast after commit. Init creates details, tech stack, sections,
        //    labels, tasks, and widgets in one shot, so we fire a single
        //    ProjectInitialized event; clients refetch every affected surface
        //    from it rather than reacting to a burst of per-entity events.
        $project->refresh();
        broadcast(new ProjectInitialized($project, $projectId));

        // 5. One activity row total — keeps the feed readable.
        $c = $result['counts'];
        app(ActivityLogService::class)->log(
            projectId:    $projectId,
            userId:       $userId,
            eventType:    'project.initialized',
            subjectType:  'project',
            subjectLabel: $project->name,
            subjectId:    $projectId,
            description:  "initialized project via MCP: {$c['sections']} sections, {$c['tasks']} tasks, {$c['stack']} tech stack entries",
            viaMcp:       true,
        );

        return "Initialized project: details set, {$c['stack']} tech stack entries, "
            . "{$c['sections']} sections, {$c['labels']} labels, {$c['tasks']} tasks, "
            . "{$c['widgets']} widgets placed.";
    }
}
