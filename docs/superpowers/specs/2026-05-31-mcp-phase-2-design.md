# MCP Phase 2 — Read Tools Design

**Date:** 2026-05-31
**Scope:** 5 new read tools added to the Luminite MCP server. No caching, no new infrastructure.

---

## Context

Phase 1 delivered the MCP server foundation: token auth middleware, JSON-RPC dispatcher, `McpController`, and the `get_project_context` tool. Phase 2 adds targeted read tools so Claude can pull live project data during a coding session.

---

## Architecture

No new infrastructure. All tools follow the existing pattern:

- Extend `App\Mcp\Tools\Tool`
- Implement `definition()` and `run(array $args, Request $request): string`
- Registered in `McpController::handle()` alongside `GetProjectContext`
- Scoped to `$this->projectId($request)` — never cross project boundaries
- No Redis caching — direct DB queries
- `get_project_context` stays unchanged

---

## Tools

### 1. `get_tasks`

Returns all tasks for the project. Optional filters.

**Args:**
- `status?` (string) — filter by task status
- `priority?` (string) — filter by priority
- `label?` (string) — filter by label name, case-insensitive

**Eager loads:** section, assignee, labels

**Output format** (plain text, grouped by section):
```
Section: Backlog
- [todo] Build login page (high) — assigned: Pierson — due: 2026-06-10 — labels: frontend, auth
- [in_progress] Fix CORS issue (medium) — assigned: unassigned

Section: In Review
- [review] Add rate limiting (low) — assigned: Pierson
```

---

### 2. `get_task_sections`

Returns all sections for the project ordered by position. No args.

**Output format:**
```
Sections:
1. Backlog
2. In Progress
3. In Review
4. Done
```

---

### 3. `get_project_notes`

Returns all notes with title and 200-character preview. Optional label filter.

**Args:**
- `label?` (string) — filter by label name, case-insensitive

**Output format:**
```
[ID: 14] Auth Architecture
Sanctum token-based auth. Permanent tokens stored in localStorage. No sessions, no cookies...

[ID: 22] Deployment Notes
GitHub Actions push-to-deploy. Main branch triggers production deploy via...
```

The preview is plain text — extracted from the Tiptap JSON document stored in `content` by recursively collecting all `text` node values. ID is included so Claude can follow up with `get_note`.

---

### 4. `get_note`

Returns full content of a single note. Plain text extracted from Tiptap JSON by recursively collecting all `text` node values. Capped at 5,000 characters.

**Args:**
- `note_id` (integer, required)

**Truncation:** If content exceeds 5,000 characters, output is cut at 5,000 and appended with:
`[Note truncated at 5,000 characters. Full note is longer.]`

**Scoping:** Returns an error if the note doesn't belong to this project.

---

### 5. `get_recent_activity`

Returns recent activity log entries, newest first.

**Args:**
- `limit?` (integer) — default 20, max 50. Values above 50 are silently clamped to 50.

**Output format:**
```
2026-05-31 14:22 — Pierson created task "Fix CORS issue"
2026-05-31 13:10 — Pierson completed task "Add rate limiting" [via MCP]
2026-05-30 09:05 — Pierson added note "Deployment Notes"
```

`[via MCP]` appended when `via_mcp` is true on the activity log entry.

---

## Error Handling

All errors return as plain text inside the standard `content[0].text` envelope — no exceptions bubble to JSON-RPC error format unless the dispatcher itself fails.

| Situation | Response |
|---|---|
| Project deleted/not found | `"Error: project not found."` |
| `note_id` not found or wrong project | `"Error: note not found."` |
| `limit` above 50 | Silently clamped to 50 |

---

## Registration

All 5 tools added to the array in `McpController::handle()`:

```php
$server = new McpServer([
    new GetProjectContext(),
    new GetTasks(),
    new GetTaskSections(),
    new GetProjectNotes(),
    new GetNote(),
    new GetRecentActivity(),
]);
```

---

## Out of Scope (Phase 2)

- Redis caching (deferred indefinitely)
- Write tools (`complete_task`, `create_task`, `sync_todos`, `log_session_activity`) — Phase 3
- OAuth 2.1 — Phase 4
- `get_time_entries`, `get_labels` — not planned for Phase 2
