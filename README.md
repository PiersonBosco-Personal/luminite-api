# luminite-api

The Laravel backend for **[Luminite](https://github.com/PiersonBosco-Personal/luminite-web-app)** — a
project workspace for small development teams. Three jobs in one service:

1. A **versioned REST API** under `/api/v1/` serving the React frontend.
2. A **WebSocket server** (Laravel Reverb) broadcasting every write in real time.
3. An **MCP server** at `POST /mcp` that gives a coding agent read/write access to the project's
   tasks, decisions, and memory.

Fully decoupled from the frontend — no Blade views, no Inertia, no server-rendered JavaScript.

---

## Stack

| | |
|---|---|
| **Framework** | Laravel 12, PHP 8.2+ |
| **Database** | PostgreSQL 16 |
| **Vector search** | `pgvector` as an extension of that same database — no second connection |
| **Real-time** | Laravel Reverb (Pusher protocol, self-hosted) |
| **Auth** | Laravel Sanctum — long-lived bearer tokens |
| **Queues** | Database-backed Laravel queues, drained by a dedicated worker container |
| **AI** | Provider-agnostic `AIProvider` interface; OpenAI embeddings are one implementation |
| **Local dev** | Docker via Laravel Sail |
| **Tests** | Pest / PHPUnit — 102 test files, SQLite in-memory |
| **CI/CD** | GitHub Actions, push-to-deploy |

---

## What's in here

**24 Eloquent models** — projects, tasks, sections, notes, folders, labels, attachments, time
entries, work types, activity logs, decisions, thread entries, embeddings, MCP tokens, and more.

**31 broadcast events.** Every mutation — a task moved, a label renamed, a timer started, an
invitation accepted — fires an event on one of four channels: a private project channel, a project
presence channel, a per-note presence channel, and a private user channel.

**An audited write path.** Writes route through `ActivityLogService`, which records who changed
what and fires an `ActivityCreated` event — that pair is what feeds the live activity stream and the
team changelog. It debounces repeated edits to the same field by the same user inside a five-minute
window, so renaming a task three times leaves one entry in the feed instead of three. All twelve
writing MCP tools log through it, as do the project, task, section, label, and invitation
controllers. Validation lives in 24 Form Request classes.

### The MCP server

`app/Mcp/` implements Model Context Protocol over a single `POST /mcp` endpoint — the one route
outside the `/api/v1/` prefix, since it carries its own token middleware. It exposes **20 tools** an
agent can call mid-session:

- **Tasks** — `create_task`, `update_task`, `complete_task`, `get_open_tasks`
- **Memory** — `log_decision`, `add_thread_entry`, `get_thread`, `get_decisions`
- **Notes** — `create_note`, `update_note`, `get_project_notes`
- **Context** — `get_session_context`, `get_recent_activity`, `log_session_activity`
- **Semantic search** — `recall`
- **Structure** — `initialize_project`, `manage_section`, `manage_label`, `get_sections`, `get_labels`

`recall` is the interesting one. Tasks, decisions, and the gotchas/dead-ends worth remembering are
embedded into `pgvector` **on write**, not on a nightly batch — a decision logged seconds ago has to
be findable immediately, or the agent's memory is useless within a single session. The
`EmbedRecord` job collapses edit churn with a unique-job window, so a note that autosaves every
1.5 seconds costs one embedding call instead of hundreds.

The provider sits behind an `AIProvider` interface, so swapping embedding vendors is a container
binding, not a refactor.

---

## Structure

```
app/
├── AI/           Provider contract + implementations
├── Events/       31 broadcast events
├── Http/         Controllers (Api/V1), Form Requests, middleware
├── Jobs/         Queued work — embeddings, attachment cleanup
├── Mcp/          MCP server: Tools/, Resources/, McpServer
├── Models/       24 Eloquent models
└── Services/     ActivityLog, TaskCompletion, WidgetPlacement
```

## Running locally

```bash
composer install
cp .env.example .env && php artisan key:generate
./vendor/bin/sail up -d        # PostgreSQL + Reverb + queue worker
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
```

See [TESTING.md](TESTING.md) for the test setup.

---

Built and maintained by **[Pierson Bosco](https://github.com/PiersonBosco-Personal)**, with a handful
of commits from one other developer.
