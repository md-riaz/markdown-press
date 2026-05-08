<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken() ?? $request->header('X-API-Token');

        if (!$raw) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = ApiToken::where('token', hash('sha256', $raw))
            ->with('user')
            ->first();

        if (!$token || $token->isExpired() || !$token->isAllowedFromIp($request->ip())) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token->update(['last_used_at' => now()]);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
