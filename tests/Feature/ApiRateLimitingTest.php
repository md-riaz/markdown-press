<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->rawToken = Str::random(64);

        ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'rate-limit-test',
            'token' => hash('sha256', $this->rawToken),
            'abilities' => ['*'],
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_public_api_routes_are_limited_to_sixty_requests_per_minute(): void
    {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->getJson('/api/v1/posts')
                ->assertOk()
                ->assertHeader('X-RateLimit-Limit', '60');
        }

        $this->getJson('/api/v1/posts')
            ->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('Retry-After');
    }

    public function test_authenticated_api_routes_are_limited_to_three_hundred_requests_per_minute(): void
    {
        $this->getJson('/api/v1/auth/tokens', $this->authHeaders())
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '300');

        $limiterKey = md5('api-authenticated'.'api:token:'.hash('sha256', $this->rawToken));

        RateLimiter::clear($limiterKey);
        RateLimiter::increment($limiterKey, 60, 300);

        $this->getJson('/api/v1/auth/tokens', $this->authHeaders())
            ->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '300')
            ->assertHeader('Retry-After');
    }

    public function test_auth_token_endpoint_is_limited_to_five_requests_per_minute_per_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/token', [
                'email' => $this->user->email,
                'password' => 'password',
                'token_name' => "token-{$attempt}",
            ])->assertOk()
                ->assertHeader('X-RateLimit-Limit', '5');
        }

        $this->postJson('/api/v1/auth/token', [
            'email' => $this->user->email,
            'password' => 'password',
            'token_name' => 'blocked-token',
        ])->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('Retry-After');
    }

    public function test_failed_auth_token_attempts_also_count_toward_the_rate_limit(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/token', [
                'email' => $this->user->email,
                'password' => 'wrong-password',
                'token_name' => "invalid-{$attempt}",
            ])->assertStatus(401)
                ->assertHeader('X-RateLimit-Limit', '5');
        }

        $this->postJson('/api/v1/auth/token', [
            'email' => $this->user->email,
            'password' => 'password',
            'token_name' => 'still-blocked',
        ])->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('Retry-After');
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->rawToken}"];
    }
}
