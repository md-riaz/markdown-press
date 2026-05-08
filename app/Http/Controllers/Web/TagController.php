<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Modules\Post\PostService;

class TagController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function show(string $slug)
    {
        $tag       = Tag::where('slug', $slug)->firstOrFail();
        $paginator = $this->posts->paginate(12, ['tag' => $slug]);
        return view('blog.tag', compact('tag', 'paginator'));
    }
}
