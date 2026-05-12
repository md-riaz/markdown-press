<?php
namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use App\Modules\Post\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_reverts_post_to_revision_content(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id'          => $user->id,
            'content_markdown' => 'Original content',
        ]);

        $service  = app(RevisionService::class);
        $revision = $service->save($post);

        $post->update(['content_markdown' => 'Updated content']);
        $this->assertSame('Updated content', $post->fresh()->content_markdown);

        $service->restore($post, $revision->id);
        $this->assertSame('Original content', $post->fresh()->content_markdown);
    }
}
