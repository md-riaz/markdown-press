<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Post\PostService;

class AuthorController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function show(string $username)
    {
        $author    = User::where('username', $username)->firstOrFail();
        $paginator = $this->posts->paginate(12, ['author' => $username]);
        return view('blog.author', compact('author', 'paginator'));
    }
}
