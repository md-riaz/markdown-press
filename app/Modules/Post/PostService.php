<?php

namespace App\Modules\Post;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class PostService
{
    public function __construct(
        private MarkdownRenderer $renderer,
        private RevisionService  $revisions,
        private RenderCache      $cache,
    ) {}

    public function create(User $author, array $data): Post
    {
        $data['user_id']           = $author->id;
        $data['slug']              = $this->uniqueSlug($data['title'], $data['slug'] ?? null);
        $data['content_html_cached'] = $this->renderer->toHtml($data['content_markdown']);

        $post = Post::create($data);

        if (!empty($data['category_ids'])) {
            $post->categories()->sync($data['category_ids']);
        }
        if (!empty($data['tag_ids'])) {
            $post->tags()->sync($data['tag_ids']);
        }

        return $post;
    }

    public function update(Post $post, array $data): Post
    {
        // Save revision before changing
        $this->revisions->save($post);

        if (isset($data['title']) && !isset($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['title'], null, $post->id);
        }

        if (isset($data['content_markdown'])) {
            $data['content_html_cached'] = $this->renderer->toHtml($data['content_markdown']);
        }

        $post->update($data);
        $this->cache->invalidate($post);

        if (isset($data['category_ids'])) {
            $post->categories()->sync($data['category_ids']);
        }
        if (isset($data['tag_ids'])) {
            $post->tags()->sync($data['tag_ids']);
        }

        return $post->fresh();
    }

    public function delete(Post $post): void
    {
        $this->cache->invalidate($post);
        $post->delete();
    }

    public function clone(Post $post, User $cloner): Post
    {
        $newSlug = $this->uniqueSlug($post->title . ' Copy');

        $cloned = $post->replicate(['id', 'slug', 'published_at', 'view_count', 'comment_count']);
        $cloned->slug        = $newSlug;
        $cloned->status      = 'draft';
        $cloned->user_id     = $cloner->id;
        $cloned->published_at = null;
        $cloned->save();

        $cloned->categories()->sync($post->categories->pluck('id'));
        $cloned->tags()->sync($post->tags->pluck('id'));

        return $cloned;
    }

    public function publish(Post $post): Post
    {
        $post->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        return $post->fresh();
    }

    public function schedule(Post $post, \DateTimeInterface $at): Post
    {
        $post->update([
            'status'       => 'scheduled',
            'scheduled_at' => $at,
        ]);

        return $post->fresh();
    }

    public function findBySlug(string $slug): ?Post
    {
        return Post::where('slug', $slug)->with(['author', 'categories', 'tags', 'featuredImage'])->first();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Post::published()
            ->with(['author', 'categories', 'tags', 'featuredImage'])
            ->latest('published_at');

        if (!empty($filters['category'])) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $filters['category']));
        }
        if (!empty($filters['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $filters['tag']));
        }
        if (!empty($filters['author'])) {
            $query->whereHas('author', fn ($q) => $q->where('username', $filters['author']));
        }

        return $query->paginate($perPage);
    }

    private function uniqueSlug(string $title, ?string $base = null, ?int $excludeId = null): string
    {
        $slug  = Str::slug($base ?? $title);
        $count = 0;
        $try   = $slug;

        while (true) {
            $query = Post::where('slug', $try);
            if ($excludeId) $query->where('id', '!=', $excludeId);
            if (!$query->exists()) return $try;
            $try = $slug . '-' . (++$count + 1);
        }
    }
}
