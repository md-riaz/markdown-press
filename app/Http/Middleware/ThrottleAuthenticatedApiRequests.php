<?php

namespace App\Http\Middleware;

use App\Support\ApiRateLimitKey;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;

class ThrottleAuthenticatedApiRequests extends ThrottleRequests
{
    private const AUTHENTICATED_LIMIT = 300;

    private const MISSING_TOKEN_LIMIT = 10;

    private const DECAY_SECONDS = 60;

    public function __construct(RateLimiter $limiter)
    {
        parent::__construct($limiter);
    }

    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Token');

        $maxAttempts = $token === null ? self::MISSING_TOKEN_LIMIT : self::AUTHENTICATED_LIMIT;
        $key = $token === null
            ? ApiRateLimitKey::missingAuthenticatedToken($request->ip())
            : ApiRateLimitKey::authenticated($token);

        return $this->handleRequest($request, $next, [(object) [
            'key' => $key,
            'maxAttempts' => $maxAttempts,
            'decaySeconds' => self::DECAY_SECONDS,
            'afterCallback' => null,
            'responseCallback' => null,
        ]]);
    }
}
