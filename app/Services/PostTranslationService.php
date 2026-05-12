<?php
namespace App\Services;

use App\Models\Post;
use App\Models\PostTranslation;
use RuntimeException;

class PostTranslationService
{
    public function create(Post $post, string $locale, array $data): PostTranslation
    {
        if ($post->translations()->where('locale', $locale)->exists()) {
            throw new RuntimeException("Translation for locale [{$locale}] already exists for post [{$post->id}].");
        }

        return $post->translations()->create(array_merge($data, ['locale' => $locale]));
    }

    public function update(PostTranslation $translation, array $data): PostTranslation
    {
        $translation->update($data);
        return $translation->fresh();
    }

    public function delete(PostTranslation $translation): void
    {
        $translation->delete();
    }

    public function findByPostAndLocale(Post $post, string $locale): ?PostTranslation
    {
        return $post->translations()->where('locale', $locale)->first();
    }
}
