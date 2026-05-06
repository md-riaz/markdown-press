<?php

namespace App\Modules\Theme;

use App\Contracts\ThemeRendererContract;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Facades\View;

class ThemeRenderer implements ThemeRendererContract
{
    public function renderPost(Post $post, Theme $theme, string $locale = 'en'): string
    {
        $view = "themes.{$theme->slug}.post";

        if (!View::exists($view)) {
            $view = 'themes.default.post';
        }

        return view($view, compact('post', 'theme', 'locale'))->render();
    }

    public function renderIndex(array $posts, Theme $theme, int $page = 1): string
    {
        $view = "themes.{$theme->slug}.index";

        if (!View::exists($view)) {
            $view = 'themes.default.index';
        }

        return view($view, compact('posts', 'theme', 'page'))->render();
    }

    public function renderCategory(Category $category, array $posts, Theme $theme): string
    {
        $view = "themes.{$theme->slug}.category";

        if (!View::exists($view)) {
            $view = 'themes.default.category';
        }

        return view($view, compact('category', 'posts', 'theme'))->render();
    }

    public function renderTag(Tag $tag, array $posts, Theme $theme): string
    {
        $view = "themes.{$theme->slug}.tag";

        if (!View::exists($view)) {
            $view = 'themes.default.tag';
        }

        return view($view, compact('tag', 'posts', 'theme'))->render();
    }

    public function renderAuthor(User $author, array $posts, Theme $theme): string
    {
        $view = "themes.{$theme->slug}.author";

        if (!View::exists($view)) {
            $view = 'themes.default.author';
        }

        return view($view, compact('author', 'posts', 'theme'))->render();
    }
}
