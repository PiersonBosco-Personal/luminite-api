<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidateMcpToken
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return $this->error($request, 'Unauthorized: missing token');
        }

        $token = McpToken::where('token', hash('sha256', $bearer))->first();

        if (! $token || $token->isExpired()) {
            return $this->error($request, 'Unauthorized: invalid or expired token');
        }

        $token->update([
            'request_count' => DB::raw('request_count + 1'),
            'last_used_at'  => now(),
        ]);

        $request->attributes->set('mcp_user_id', $token->user_id);
        $request->attributes->set('mcp_project_id', $token->project_id);

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
