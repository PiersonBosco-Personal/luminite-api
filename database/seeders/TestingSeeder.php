<?php

namespace Database\Seeders;

use App\Models\Label;
use App\Models\Project;
use App\Models\TaskSection;
use App\Models\TechStack;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('widgets')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->call(WidgetSeeder::class);

        $owner = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('asdfasdf')],
        );

        $goals =
            <<<EOT
                Luminite is a downloadable desktop application for small web development teams that unifies project management into a single, purpose-built tool. The product vision combines the best elements of Trello (kanban task management), Obsidian (rich, interconnected notetaking), and Notion (customizable dashboards) — but built specifically for the workflows and mental models of developers.

                Core product goals:

                1. Unified project context — Everything about a project (tasks, notes, tech stack, architecture decisions, files, team presence) lives in one place. Developers should never need to context-switch between five tools to answer "what are we building and where are we at?"

                2. AI-native from the ground up — Luminite is not a project management tool with AI bolted on. The AI has deep, structured access to real project data: every task, every note, the tech stack, architecture decisions, and team activity. The AI can answer project questions, summarize state, suggest next steps, and create tasks/notes on behalf of the user. The AI provider is abstracted behind an interface so any model (OpenAI, Anthropic, Ollama) can be swapped without code changes.

                3. MCP integration — Luminite exposes a Model Context Protocol (MCP) server so that when a developer runs Claude Code inside their codebase, Claude already has live access to their Luminite project data. Tasks, notes, tech stack, and decisions are available without copy-pasting. Claude can mark tasks done and create new ones as it works. This is the bridge between where the project is managed (Luminite) and where the work happens (the terminal).

                4. Real-time collaboration — Small teams see each other's presence, task updates, and note edits in real time via WebSockets (Laravel Reverb). No page refreshes, no stale state.

                5. Desktop-first distribution — Luminite ships as a downloadable PWA app. Users download it once; it connects to infrastructure we own and operate. Users never run a database or backend themselves. This gives us full control over the stack while keeping the install experience simple.

                6. Developer-specific features — Tech stack registry (version-tracked), architecture notes per project, attachment storage with folder trees, rich text notes with task linking, and a customizable widget dashboard that surfaces the information most relevant to each team member.
            EOT;

        $architectureNotes =
            <<<EOT
                ## Repository Structure

                Luminite is split into three completely separate repositories:

                - luminite-api/ — Laravel 11 REST API (PHP 8.3). Runs in Docker via Laravel Sail locally. Deployed to DigitalOcean via Laravel Forge in staging and production. This is the only process that talks to the database.
                - luminite-web-app/ — React 18 + TypeScript frontend. Vite dev server locally. Communicates with the API exclusively via HTTP and WebSockets. No server-side rendering. No Inertia.js.
                - luminite-docs/ — Markdown documentation, design assets, and product collateral. Published as a public repo and website to showcase the project vision and technical approach.

                Laravel and React are fully decoupled. They never share code. Communication is HTTP API calls and WebSocket connections only.

                ---

                ## Locked Architectural Decisions

                **Auth:** Laravel Sanctum, token-based. No sessions, no cookies. Tokens are stored in localStorage during browser development. When Electron is added, all token storage swaps to window.electronAPI.safeStorage via a single change in customAxios.ts. This is by design — a one-file migration path.

                **API versioning:** All routes are prefixed /api/v1/. A clean v2 migration path is preserved from day one.

                **No Inertia.js:** Never. Laravel is a pure API. React is a pure SPA. They are independently deployable and independently testable.

                **No SSR:** Every frontend decision must be compatible with Electron's renderer process. No server-dependent rendering, ever.

                **Real-time:** Laravel Reverb (WebSockets, Pusher-compatible protocol). Pusher is a documented swap but not the default. Laravel Echo on the frontend.

                **AI provider abstraction:** All AI calls go through a single AIProvider interface (app/AI/Contracts/AIProvider.php). Concrete implementations exist for OpenAI (GPT-4o), Anthropic (Claude), and Ollama (local). The active provider is a one-line config change. Never call any AI SDK directly from controllers.

                **Database split:** MySQL is the primary database for all relational data. PostgreSQL + pgvector is added only for AI vector embeddings and runs as a separate connection. Do not conflate the two.

                **Queue/Jobs:** Laravel Queues backed by Redis. Used for AI processing jobs and async tasks like file deletion. Never run AI inference synchronously in a request.

                **UI:** Shadcn/ui + Tailwind CSS. Custom components are preferred. Do not introduce MUI, Chakra, or other component libraries.

                **Docker scope:** Docker (Laravel Sail) is for local development only. Staging and production run on DigitalOcean managed by Laravel Forge. Never write deployment instructions that assume Docker on the server.

                **Middleware:** All project-scoped API routes require auth:sanctum + the EnsureProjectMember middleware, which verifies the authenticated user is an active member of the requested project.

                ---

                ## MCP Server (Planned)

                Luminite will expose an MCP server (published as an npm package: luminite-mcp) that developers add to their ~/.claude/settings.json once. From that point, every Claude Code session has live read/write access to the Luminite project: tasks, sections, notes, tech stack, labels. Write tools allow Claude to create tasks, update task status, and create notes directly as it works. Auth is a per-user API token generated in Luminite settings.

                ---

                ## Deployment & CI/CD

                - Environments: local (Docker), staging (DigitalOcean), production (DigitalOcean)
                - Laravel Forge manages server provisioning, deploys, and SSL
                - GitHub Actions triggers Forge webhook on push to the relevant branch
                - Branch strategy: main → production, develop → staging
            EOT;

        $luminite = Project::create([
            'owner_id'           => $owner->id,
            'name'               => 'Luminite',
            'description'        => 'Luminite is a downloadable desktop application for small web development teams. It is a monorepo project combining a Laravel REST API (luminite-api), a React + TypeScript frontend (luminite-web-app), and a planned PWA download through a frontend webstie. The platform unifies kanban task management, rich notetaking, customizable dashboards, real-time collaboration, and AI-powered project assistance into a single tool built specifically for developer workflows.',
            'status'             => 'active',
            'goals'              => $goals,
            'architecture_notes' => $architectureNotes,
        ]);

        $luminite->members()->attach($owner->id, ['role' => 'owner']);

        TechStack::insert([
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Laravel',        'version' => '11',    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'PHP',             'version' => '8.3',   'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'MySQL',           'version' => '8.0',   'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'PostgreSQL',      'version' => '16',    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'pgvector',        'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Laravel Reverb',  'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Laravel Sanctum', 'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Redis',           'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Docker',          'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'React',           'version' => '18',    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'TypeScript',      'version' => '5.x',   'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Vite',            'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Tailwind CSS',    'version' => '4.0',   'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Shadcn/ui',       'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Electron',        'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'DigitalOcean',    'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'Laravel Forge',   'version' => null,    'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'parent_id' => null, 'name' => 'GitHub Actions',  'version' => null,    'created_at' => now(), 'updated_at' => now()],

        ]);

        $featureLabel  = Label::create(['project_id' => $luminite->id, 'name' => 'Feature',     'color' => '#6366f1']);
        $bugLabel      = Label::create(['project_id' => $luminite->id, 'name' => 'Bug',         'color' => '#ef4444']);
        $backendLabel  = Label::create(['project_id' => $luminite->id, 'name' => 'Backend',     'color' => '#10b981']);
        $frontendLabel = Label::create(['project_id' => $luminite->id, 'name' => 'Frontend',    'color' => '#3b82f6']);
        $aiLabel       = Label::create(['project_id' => $luminite->id, 'name' => 'AI',          'color' => '#8b5cf6']);
        $mcpLabel      = Label::create(['project_id' => $luminite->id, 'name' => 'MCP',         'color' => '#f59e0b']);
        $electronLabel = Label::create(['project_id' => $luminite->id, 'name' => 'Electron',    'color' => '#64748b']);
        $infraLabel    = Label::create(['project_id' => $luminite->id, 'name' => 'Infra',       'color' => '#06b6d4']);

        TaskSection::insert([
            ['project_id' => $luminite->id, 'name' => 'Backlog',     'position' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'name' => 'In Progress', 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'name' => 'Review',      'position' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $luminite->id, 'name' => 'Done',        'position' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
