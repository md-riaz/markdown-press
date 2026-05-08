<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Setting;

class PageViewController extends Controller
{
    public function sitemap()
    {
        $posts = Post::published()->latest('published_at')->get(['slug', 'updated_at']);
        return response(view('sitemap', compact('posts'))->render(), 200)
            ->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $content = "User-agent: *\nAllow: /\nSitemap: " . url('/sitemap.xml');
        return response($content, 200)->header('Content-Type', 'text/plain');
    }
}
