<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTokenListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->rawToken = Str::random(64);

        ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'first-token',
            'token' => hash('sha256', $this->rawToken),
            'abilities' => ['*'],
            'expires_at' => now()->addYear(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->rawToken}"];
    }

    public function test_token_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/tokens')
            ->assertStatus(401);
    }

    public function test_token_list_returns_only_authenticated_users_tokens(): void
    {
        $otherUser = User::factory()->create();

        ApiToken::create([
            'user_id' => $otherUser->id,
            'name' => 'other-token',
            'token' => hash('sha256', Str::random(64)),
            'abilities' => ['*'],
            'expires_at' => now()->addYear(),
            'created_at' => now()->subSeconds(30),
            'updated_at' => now()->subSeconds(30),
        ]);

        ApiToken::create([
            'user_id' => $this->user->id,
            'name' => 'second-token',
            'token' => hash('sha256', Str::random(64)),
            'abilities' => ['*'],
            'expires_at' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/auth/tokens', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'second-token');
        $response->assertJsonPath('data.1.name', 'first-token');
        $this->assertSame(['second-token', 'first-token'], array_column($response->json('data'), 'name'));
    }

    public function test_token_list_includes_metadata_without_raw_token_value(): void
    {
        $response = $this->getJson('/api/v1/auth/tokens', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [[
                'id',
                'name',
                'abilities',
                'last_used_at',
                'expires_at',
                'created_at',
                'updated_at',
            ]],
        ]);
        $this->assertArrayNotHasKey('token', $response->json('data.0'));
    }
}
