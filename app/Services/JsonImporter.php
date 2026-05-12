<?php
namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;

class JsonImporter
{
    public function import(array $data, string $mode = 'append'): array
    {
        $result = ['imported' => 0, 'skipped' => 0];

        if ($mode === 'fresh') {
            Post::query()->forceDelete();
        }

        $defaultUser = User::where('role', 'admin')->first();

        foreach ($data as $item) {
            $slug = $item['slug'] ?? null;
            if (!$slug) { $result['skipped']++; continue; }

            if ($mode === 'append' && Post::where('slug', $slug)->exists()) {
                $result['skipped']++;
                continue;
            }

            $post = Post::create([
                'user_id'          => $defaultUser?->id ?? 1,
                'title'            => $item['title'] ?? 'Untitled',
                'slug'             => $slug,
                'content_markdown' => $item['content_markdown'] ?? '',
                'status'           => $item['status'] ?? 'draft',
                'published_at'     => $item['published_at'] ?? null,
            ]);

            if (!empty($item['categories'])) {
                $catIds = collect($item['categories'])->map(function ($slug) {
                    return Category::firstOrCreate(['slug' => $slug], ['name' => $slug])->id;
                });
                $post->categories()->sync($catIds);
            }

            if (!empty($item['tags'])) {
                $tagIds = collect($item['tags'])->map(function ($slug) {
                    return Tag::firstOrCreate(['slug' => $slug], ['name' => $slug])->id;
                });
                $post->tags()->sync($tagIds);
            }

            $result['imported']++;
        }

        return $result;
    }
}
