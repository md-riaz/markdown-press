<?php

namespace Tests\Unit;

use App\Contracts\AIDriverContract;
use App\Models\Post;
use App\Models\User;
use App\Modules\AI\Jobs\GenerateExcerptJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateExcerptJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_post_meta_description_with_generated_excerpt(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'meta_description' => null,
        ]);

        $ai = new class implements AIDriverContract {
            public function summarize(string $markdown): string { return 'summary'; }
            public function excerpt(string $markdown, int $words = 50): string { return "excerpt {$words}"; }
            public function translate(string $markdown, string $targetLocale, string $sourceLocale = 'en'): string { return 'translated'; }
        };

        (new GenerateExcerptJob($post, 42))->handle($ai);

        $post->refresh();
        $this->assertSame('excerpt 42', $post->meta_description);
    }
}
