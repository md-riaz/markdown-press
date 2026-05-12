<?php
namespace App\Services;

use RuntimeException;

class SsrfGuard
{
    public function validate(string $url, bool $requireAllowlist = true): void
    {
        $parsed = parse_url($url);
        if (!$parsed || !in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
            throw new RuntimeException("Invalid URL scheme: only http/https allowed.");
        }

        $host = $parsed['host'] ?? '';
        if (!$host) throw new RuntimeException("Missing host in URL.");

        if ($requireAllowlist) {
            $allowed = config('media.ssrf_allowed_hosts', []);
            if (!in_array($host, $allowed, true)) {
                throw new RuntimeException("Host [{$host}] is not in the SSRF allowlist.");
            }
        }

        $ip = gethostbyname($host);
        $this->assertNotPrivate($ip);
    }

    private function assertNotPrivate(string $ip): void
    {
        $privateRanges = [
            '/^127\./',
            '/^10\./',
            '/^172\.(1[6-9]|2[0-9]|3[01])\./',
            '/^192\.168\./',
            '/^169\.254\./',
            '/^::1$/',
            '/^fc/',
            '/^fd/',
        ];
        foreach ($privateRanges as $pattern) {
            if (preg_match($pattern, $ip)) {
                throw new RuntimeException("Resolved IP [{$ip}] is in a private/loopback range.");
            }
        }
    }
}
