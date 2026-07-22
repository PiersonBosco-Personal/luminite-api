<?php

namespace App\Mcp\Tools;

use App\AI\Contracts\AIProvider;
use App\Models\Decision;
use App\Models\ThreadEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Recall extends Tool
{
    private const DEFAULT_LIMIT = 8;
    private const MAX_LIMIT = 25;

    public function definition(): array
    {
        return [
            'name'        => 'recall',
            'description' => 'Semantically search the project\'s durable memory — active decisions and the gotchas / dead-ends worth remembering — by meaning, not keyword. Use it to answer "have we hit this error before?", "what did we decide about X?", "did we already rule this out?". Returns the closest few matches, most relevant first. Superseded decisions are excluded. The #id shown is for your tool calls only — refer to items by their text when talking to the user.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'What you are looking for, in natural language.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results. Default 8, max 25.'],
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

        // Similarity search is a Postgres-only feature. Off Postgres (e.g. the
        // SQLite test/dev environment) there is no vector index to search.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return 'No indexed memory to search in this environment (semantic recall runs on the Postgres deployment).';
        }

        $limit = max(1, min((int) ($args['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));

        $vector  = app(AIProvider::class)->embed($query);
        $literal = '[' . implode(',', array_map('floatval', $vector)) . ']';

        // Over-fetch a little so active-filtering superseded decisions can't
        // starve the result set below the requested limit.
        $rows = DB::table('embeddings')
            ->select('source_type', 'source_id')
            ->where('project_id', $projectId)
            ->orderByRaw("embedding <=> '{$literal}'::vector")
            ->limit($limit * 2)
            ->get();

        if ($rows->isEmpty()) {
            return 'No indexed memory yet — recall becomes useful once decisions and gotchas accumulate.';
        }

        $lines = ['Recall — closest matches to your query:'];
        $n = 0;
        foreach ($rows as $row) {
            if ($n >= $limit) {
                break;
            }
            if ($row->source_type === 'decision') {
                $d = Decision::where('project_id', $projectId)->whereKey($row->source_id)->first();
                if (! $d || $d->status === 'superseded') {
                    continue; // active-filter: superseded decisions never surface in default recall
                }
                $lines[] = '- [decision] #' . $d->id . ' ' . $d->decision . ' — ' . $d->rationale;
                $n++;
            } else {
                $e = ThreadEntry::where('project_id', $projectId)->whereKey($row->source_id)->first();
                if (! $e) {
                    continue;
                }
                $lines[] = '- [' . $e->type . '] #' . $e->id . ' ' . $e->content;
                $n++;
            }
        }

        if ($n === 0) {
            return 'No indexed memory matched your query.';
        }

        return implode("\n", $lines);
    }
}
