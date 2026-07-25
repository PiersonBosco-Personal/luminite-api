<?php

namespace App\Mcp\Tools;

use App\AI\Contracts\AIProvider;
use App\Models\Decision;
use App\Models\Task;
use App\Models\ThreadEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Recall extends Tool
{
    private const DEFAULT_LIMIT = 8;
    private const MAX_LIMIT = 25;
    private const ALL_TYPES = ['decision', 'gotcha', 'dead_end', 'task'];

    public function definition(): array
    {
        return [
            'name'        => 'recall',
            'description' => 'Semantically search this project by meaning, not keyword — across active decisions, the gotchas and dead-ends worth remembering, and tasks. Use it to answer "have we hit this error before?", "what did we decide about X?", "did we already rule this out?", "is there already a task for this?". Each result carries a cosine distance: lower is closer. Disregard weak matches rather than forcing them into an answer, and say plainly when nothing relevant came back. Superseded decisions are never returned. The #id shown is for your tool calls only — refer to items by their text when talking to the user.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'What you are looking for, in natural language.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results. Default 8, max 25.'],
                    'types' => [
                        'type'        => 'array',
                        'description' => 'Restrict the search to certain kinds of record. Omit to search everything.',
                        'items'       => ['type' => 'string', 'enum' => ['decision', 'gotcha', 'dead_end', 'task']],
                    ],
                ],
                'required' => ['query'],
            ],
        ];
    }

    public function run(array $args, Request $request): string
    {
        $projectId = $this->projectId($request);

        $query = trim((string) ($args['query'] ?? ''));
        if ($query === '') {
            return 'Error: query is required.';
        }

        $types = $args['types'] ?? self::ALL_TYPES;
        if (! is_array($types) || $types === [] || array_diff($types, self::ALL_TYPES) !== []) {
            return 'Error: types must be any of ' . implode(', ', self::ALL_TYPES) . '.';
        }

        // Similarity search is a Postgres-only feature. Off Postgres (e.g. the
        // SQLite test/dev environment) there is no vector index to search.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return 'No indexed memory to search in this environment — semantic recall requires the PostgreSQL + pgvector deployment, and this instance is running on ' . DB::connection()->getDriverName() . '.';
        }

        $limit = max(1, min((int) ($args['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));

        $vector  = app(AIProvider::class)->embed($query);
        // floatval on every element guarantees a numeric literal — no injection surface.
        $literal = '[' . implode(',', array_map('floatval', $vector)) . ']';

        [$where, $bindings] = $this->typeClauses($types);

        // Superseded decisions are filtered here in SQL rather than after the fact,
        // so the requested limit is always filled when matches exist. The old
        // approach over-fetched limit*2 and could still under-fill.
        //
        // $limit is interpolated rather than bound: PDO can bind an int as text, and
        // Postgres rejects LIMIT '8' with "argument of LIMIT must be type bigint".
        // It is already (int)-cast and clamped to 1..25 above, so there is no injection surface.
        $rows = DB::select(
            "SELECT e.source_type, e.source_id, (e.embedding <=> '{$literal}'::vector) AS distance
             FROM embeddings e
             WHERE e.project_id = ? AND ({$where})
             ORDER BY distance
             LIMIT {$limit}",
            array_merge([$projectId], $bindings)
        );

        if ($rows === []) {
            return 'No indexed memory matched your query.';
        }

        return $this->render($rows, $this->hydrate($rows, $projectId));
    }

    /**
     * Build one OR-ed SQL clause per requested type. gotcha/dead_end both live in
     * `thread_entries` under source_type='thread_entry', so they need a subquery on
     * the entry's own type column; the embeddings table doesn't carry it.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function typeClauses(array $types): array
    {
        $clauses  = [];
        $bindings = [];

        foreach ($types as $type) {
            if ($type === 'task') {
                $clauses[] = "e.source_type = 'task'";
            } elseif ($type === 'decision') {
                $clauses[] = "(e.source_type = 'decision' AND NOT EXISTS (
                    SELECT 1 FROM decisions d WHERE d.id = e.source_id AND d.status = 'superseded'
                ))";
            } else { // gotcha | dead_end
                $clauses[]  = "(e.source_type = 'thread_entry' AND EXISTS (
                    SELECT 1 FROM thread_entries te WHERE te.id = e.source_id AND te.type = ?
                ))";
                $bindings[] = $type;
            }
        }

        return [implode(' OR ', $clauses), $bindings];
    }

    /**
     * Batch-hydrate source rows — one query per source type, not one per result.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function hydrate(array $rows, int $projectId): array
    {
        $ids = ['decision' => [], 'thread_entry' => [], 'task' => []];
        foreach ($rows as $row) {
            $ids[$row->source_type][] = $row->source_id;
        }

        return [
            'decision'     => Decision::where('project_id', $projectId)->whereIn('id', $ids['decision'])->get()->keyBy('id'),
            'thread_entry' => ThreadEntry::where('project_id', $projectId)->whereIn('id', $ids['thread_entry'])->get()->keyBy('id'),
            'task'         => Task::where('project_id', $projectId)->whereIn('id', $ids['task'])->get()->keyBy('id'),
        ];
    }

    private function render(array $rows, array $sources): string
    {
        $lines = ['Recall — closest matches (lower distance = closer):'];

        foreach ($rows as $row) {
            $model = $sources[$row->source_type][$row->source_id] ?? null;
            if (! $model) {
                continue; // source deleted between indexing and this read
            }

            $distance = number_format((float) $row->distance, 2);

            $lines[] = match ($row->source_type) {
                'decision'     => "- [{$distance}] [decision] #{$model->id} {$model->decision} — {$model->rationale}",
                'thread_entry' => "- [{$distance}] [{$model->type}] #{$model->id} {$model->content}",
                'task'         => "- [{$distance}] [task] #{$model->id} {$model->title} ({$model->status})",
            };
        }

        if (count($lines) === 1) {
            return 'No indexed memory matched your query.';
        }

        return implode("\n", $lines);
    }
}
