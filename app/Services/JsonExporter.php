<?php
namespace App\Services;

use App\Models\Post;

class JsonExporter
{
    public function export(): array
    {
        return Post::published()
            ->with(['categories', 'tags', 'translations'])
            ->get()
            ->map(fn ($post) => [
                'id'               => $post->id,
                'title'            => $post->title,
                'slug'             => $post->slug,
                'content_markdown' => $post->content_markdown,
                'status'           => $post->status,
                'published_at'     => $post->published_at?->toIso8601String(),
                'categories'       => $post->categories->pluck('slug')->toArray(),
                'tags'             => $post->tags->pluck('slug')->toArray(),
                'translations'     => $post->translations->map(fn ($t) => [
                    'locale'           => $t->locale,
                    'title'            => $t->title,
                    'slug'             => $t->slug,
                    'content_markdown' => $t->content_markdown,
                ])->toArray(),
            ])
            ->toArray();
    }
}
