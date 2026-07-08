<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // statefulApi() removed — token auth only, no cookie/session on API routes
        $middleware->alias([
            'project.member' => \App\Http\Middleware\EnsureProjectMember::class,
            'mcp.auth'       => \App\Http\Middleware\ValidateMcpToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Enrich every reported exception with request context + a correlation
        // id. Uncaught 500s are reported (logged) by Laravel automatically; this
        // just makes each log line answer "who, where, and which request". The
        // id is stamped on the request so render() can hand the same value back
        // to the client. Ignored exceptions (validation, 404, auth) aren't
        // reported, so this only fires for genuine server errors.
        $exceptions->context(function (): array {
            $request = request();
            $errorId = $request->attributes->get('error_id');

            if (! $errorId) {
                $errorId = (string) Str::uuid();
                $request->attributes->set('error_id', $errorId);
            }

            return array_filter([
                'error_id' => $errorId,
                'user_id'  => optional($request->user())->id,
                'method'   => $request->method(),
                'url'      => $request->fullUrl(),
                'route'    => optional($request->route())->getName(),
                'ip'       => $request->ip(),
            ], fn ($value) => $value !== null);
        });

        // For unhandled server errors on API/JSON requests, return the app's
        // {data, message, errors} envelope with the correlation id — never the
        // exception itself (safe regardless of APP_DEBUG). Exceptions that
        // already map to a meaningful HTTP status (422 validation, 404 model,
        // 401/403 auth, abort_*()) keep Laravel's normal response.
        $exceptions->render(function (\Throwable $e, Request $request) {
            $handledByFramework = $e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof ModelNotFoundException;

            if ($handledByFramework || ! ($request->is('api/*') || $request->expectsJson())) {
                return null; // fall through to Laravel's default rendering
            }

            return response()->json([
                'data'     => null,
                'message'  => 'Something went wrong on our end. Please try again — if it keeps happening, contact support and include this reference.',
                'errors'   => (object) [],
                'error_id' => $request->attributes->get('error_id') ?? (string) Str::uuid(),
            ], 500);
        });
    })->create();
