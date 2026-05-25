<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;

class ValidateMcpToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return $this->error('Unauthorized: missing token');
        }

        $token = McpToken::where('token', hash('sha256', $bearer))->first();

        if (! $token || $token->isExpired()) {
            return $this->error('Unauthorized: invalid or expired token');
        }

        $token->update([
            'last_used_at'  => now(),
            'request_count' => $token->request_count + 1,
        ]);

        $request->attributes->set('mcp_user_id', $token->user_id);
        $request->attributes->set('mcp_project_id', $token->project_id);

        return $next($request);
    }

    private function error(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32001, 'message' => $message],
            'id'      => null,
        ], 401);
    }
}
