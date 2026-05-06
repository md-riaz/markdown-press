<?php

namespace App\Contracts;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Theme;
use App\Models\User;

interface ThemeRendererContract
{
    public function renderPost(Post $post, Theme $theme, string $locale = 'en'): string;

    public function renderIndex(array $posts, Theme $theme, int $page = 1): string;

    public function renderCategory(Category $category, array $posts, Theme $theme): string;

    public function renderTag(Tag $tag, array $posts, Theme $theme): string;

    public function renderAuthor(User $author, array $posts, Theme $theme): string;
}
