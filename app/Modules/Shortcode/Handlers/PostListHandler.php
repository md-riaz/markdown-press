<?php
namespace App\Modules\Shortcode\Handlers;
use App\Contracts\ShortcodeHandlerContract;
use App\Models\Post;

class PostListHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'post_list'; }

    public function render(array $attributes, ?string $content): string
    {
        $limit    = (int) ($attributes['limit'] ?? 5);
        $category = $attributes['category'] ?? null;

        $query = Post::published()->with('categories')->latest('published_at')->limit($limit);

        if ($category) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $category));
        }

        $posts = $query->get();
        if ($posts->isEmpty()) return '<!-- post_list: no posts found -->';

        $items = '';
        foreach ($posts as $post) {
            $url   = url("/{$post->slug}");
            $title = e($post->title);
            $items .= "<li><a href=\"{$url}\">{$title}</a></li>";
        }

        return "<ul class=\"post-list\">{$items}</ul>";
    }
}
