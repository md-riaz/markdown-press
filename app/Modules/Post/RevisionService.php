<?php

namespace App\Modules\Post;

use App\Models\Post;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RevisionService
{
    private const MAX_REVISIONS = 25;

    public function save(Post $post, ?User $user = null): Revision
    {
        $revision = Revision::create([
            'post_id'          => $post->id,
            'user_id'          => ($user ?? Auth::user())?->id ?? $post->user_id,
            'title'            => $post->title,
            'content_markdown' => $post->content_markdown,
            'meta'             => [
                'status'         => $post->status,
                'meta_title'     => $post->meta_title,
                'meta_description' => $post->meta_description,
            ],
        ]);

        // Prune old revisions
        $count = Revision::where('post_id', $post->id)->count();
        if ($count > self::MAX_REVISIONS) {
            Revision::where('post_id', $post->id)
                ->oldest('created_at')
                ->limit($count - self::MAX_REVISIONS)
                ->delete();
        }

        return $revision;
    }

    public function restore(Post $post, int $revisionId): Post
    {
        $revision = Revision::where('post_id', $post->id)->findOrFail($revisionId);

        // Save current state as revision first
        $this->save($post);

        $post->update([
            'title'            => $revision->title,
            'content_markdown' => $revision->content_markdown,
        ]);

        return $post->fresh();
    }

    public function list(Post $post): \Illuminate\Database\Eloquent\Collection
    {
        return Revision::where('post_id', $post->id)
            ->with('user')
            ->latest('created_at')
            ->get();
    }
}
