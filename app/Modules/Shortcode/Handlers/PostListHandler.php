<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;
use App\Models\Post;

class PostListHandler implements ShortcodeHandlerContract
{
    public function name(): string
    {
        return 'post_list';
    }

    public function render(array $attributes, ?string $content): string
    {
        $limit = max(1, min(50, (int) ($attributes['limit'] ?? 5)));
        $category = $attributes['category'] ?? null;

        $query = Post::query()
            ->published()
            ->latest('published_at')
            ->limit($limit);

        if ($category) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $category));
        }

        $posts = $query->get(['id', 'slug', 'title']);

        if ($posts->isEmpty()) {
            return '<!-- post_list: no posts -->';
        }

        $items = $posts->map(function (Post $post) {
            $title = e($post->title);
            $url = e(url('/'.$post->slug));

            return "<li><a href=\"{$url}\">{$title}</a></li>";
        })->implode('');

        return "<ul class=\"shortcode-post-list\">{$items}</ul>";
    }
}
