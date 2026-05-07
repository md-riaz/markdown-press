<?php

namespace App\Modules\Theme;

use App\Contracts\ThemeRendererContract;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Theme;
use App\Models\User;
use App\Modules\Post\RenderCache;
use Illuminate\Support\Facades\View;

class ThemeRenderer implements ThemeRendererContract
{
    public function __construct(private RenderCache $cache) {}

    public function renderPost(Post $post, Theme $theme, string $locale = 'en'): string
    {
        $html = $this->cache->get($post, $locale);

        return view($this->resolveView($theme, 'post'), compact('post', 'theme', 'locale', 'html'))->render();
    }

    public function renderIndex(array $posts, Theme $theme, int $page = 1): string
    {
        return view($this->resolveView($theme, 'index'), compact('posts', 'theme', 'page'))->render();
    }

    public function renderCategory(Category $category, array $posts, Theme $theme): string
    {
        return view($this->resolveView($theme, 'category'), compact('category', 'posts', 'theme'))->render();
    }

    public function renderTag(Tag $tag, array $posts, Theme $theme): string
    {
        return view($this->resolveView($theme, 'tag'), compact('tag', 'posts', 'theme'))->render();
    }

    public function renderAuthor(User $author, array $posts, Theme $theme): string
    {
        return view($this->resolveView($theme, 'author'), compact('author', 'posts', 'theme'))->render();
    }

    private function resolveView(Theme $theme, string $page): string
    {
        $themeView = "themes.{$theme->slug}.{$page}";

        if (View::exists($themeView)) {
            return $themeView;
        }

        return 'themes.hello-world.'.$page;
    }
}
