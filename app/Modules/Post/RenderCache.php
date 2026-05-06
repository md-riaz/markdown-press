<?php

namespace App\Modules\Post;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class RenderCache
{
    public function __construct(private MarkdownRenderer $renderer) {}

    public function get(Post $post, string $locale = 'en'): string
    {
        $key = $this->key($post, $locale);

        return Cache::remember($key, 3600, function () use ($post, $locale) {
            if ($locale !== 'en') {
                $translation = $post->translationForLocale($locale);
                $markdown    = $translation?->content_markdown ?? $post->content_markdown;
            } else {
                $markdown = $post->content_markdown;
            }

            return $this->renderer->toHtml($markdown);
        });
    }

    public function invalidate(Post $post): void
    {
        // Invalidate all locale keys (we prefix with post id)
        Cache::forget($this->key($post, 'en'));
        // For other locales, tags or pattern flush can be used;
        // with array/file cache we flush by key convention
        foreach ($post->translations as $t) {
            Cache::forget($this->key($post, $t->locale));
        }
    }

    private function key(Post $post, string $locale): string
    {
        $hash = substr(md5($post->updated_at ?? ''), 0, 8);
        return "render:{$post->id}:{$locale}:{$hash}";
    }
}
