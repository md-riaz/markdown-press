<?php
namespace Tests\Unit;

use App\Models\User;
use App\Modules\Post\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostServiceSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_slug_appends_suffix_on_collision(): void
    {
        $user    = User::factory()->create();
        $service = app(PostService::class);

        $first = $service->create($user, [
            'title'            => 'Test Post',
            'slug'             => 'test-post',
            'content_markdown' => 'Content',
            'status'           => 'draft',
        ]);

        $second = $service->create($user, [
            'title'            => 'Test Post',
            'content_markdown' => 'Content',
            'status'           => 'draft',
        ]);

        $this->assertSame('test-post', $first->slug);
        $this->assertSame('test-post-2', $second->slug);
    }
}
