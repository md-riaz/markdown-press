<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Post\PostService;
use App\Modules\Post\RenderCache;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private PostService $posts,
        private RenderCache $cache,
    ) {}

    public function index(Request $request, int $page = 1)
    {
        $filters = $request->only(['category', 'tag', 'author']);
        $paginator = $this->posts->paginate(12, $filters);
        return view('blog.index', ['paginator' => $paginator]);
    }

    public function show(Request $request, string $slug)
    {
        $post = $this->posts->findBySlug($slug);
        if (!$post || ($post->status !== 'published' && !$request->session()->has("unlocked_{$post->id}"))) {
            abort(404);
        }

        if ($post->isPasswordProtected() && !$request->session()->has("unlocked_{$post->id}")) {
            return view('blog.password', compact('post'));
        }

        $html = $this->cache->get($post);
        $post->increment('view_count');

        return view('blog.show', compact('post', 'html'));
    }

    public function unlock(Request $request, string $slug)
    {
        $post = $this->posts->findBySlug($slug);
        if (!$post) abort(404);

        $request->validate(['password' => 'required']);

        if ($request->password !== $post->password) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put("unlocked_{$post->id}", true);
        return redirect()->route('post.show', $slug);
    }
}
