<?php
namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->rawToken = Str::random(64);

        ApiToken::create([
            'user_id'    => $this->admin->id,
            'name'       => 'test',
            'token'      => hash('sha256', $this->rawToken),
            'expires_at' => now()->addYear(),
        ]);
    }

    public function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->rawToken}"];
    }

    public function test_create_post_via_api(): void
    {
        $response = $this->postJson('/api/v1/posts', [
            'title'            => 'My New Post',
            'content_markdown' => '# Hello',
            'status'           => 'draft',
        ], $this->withToken());

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'My New Post');
        $this->assertNotEmpty($response->json('data.slug'));
    }

    public function test_get_published_posts(): void
    {
        Post::factory()->count(3)->create([
            'user_id'      => $this->admin->id,
            'status'       => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/posts');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_update_post_via_api(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->admin->id,
            'status'  => 'draft',
        ]);

        $response = $this->putJson("/api/v1/posts/{$post->slug}", [
            'title' => 'Updated Title',
        ], $this->withToken());

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_delete_post_via_api(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->admin->id,
            'status'  => 'draft',
        ]);

        $response = $this->deleteJson("/api/v1/posts/{$post->slug}", [], $this->withToken());
        $response->assertStatus(200);
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }
}
