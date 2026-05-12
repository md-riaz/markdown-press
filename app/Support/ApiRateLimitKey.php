<?php

namespace App\Support;

class ApiRateLimitKey
{
    public static function authenticated(string $token): string
    {
        return 'api:token:'.hash('sha256', $token);
    }

    public static function missingAuthenticatedToken(string $ip): string
    {
        return 'api:missing-token:'.$ip;
    }
}
