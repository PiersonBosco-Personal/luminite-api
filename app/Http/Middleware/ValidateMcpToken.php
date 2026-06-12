<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidateMcpToken
{
    private const AUTH_ERROR_MESSAGE = 'Authentication failed: your Luminite MCP token is missing, invalid, or revoked. Run `npx luminite-connect` to reconnect.';

    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return $this->error($request, self::AUTH_ERROR_MESSAGE);
        }

        $token = McpToken::where('token', hash('sha256', $bearer))->first();

        if (! $token || $token->isExpired()) {
            return $this->error($request, self::AUTH_ERROR_MESSAGE);
        }

        $token->update([
            'request_count' => DB::raw('request_count + 1'),
            'last_used_at'  => now(),
        ]);

        $request->attributes->set('mcp_user_id', $token->user_id);
        $request->attributes->set('mcp_project_id', $token->project_id);
        $request->attributes->set('mcp_token_id', $token->id);
        $request->attributes->set('mcp_scopes', $token->scopes ?? []);

        return $next($request);
    }

    private function error(Request $request, string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32001, 'message' => $message],
            'id'      => $request->json('id'),
        ], 401);
    }
}
