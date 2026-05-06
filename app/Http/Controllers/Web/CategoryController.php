<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Modules\Post\PostService;

class CategoryController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function show(string $slug)
    {
        $category  = Category::where('slug', $slug)->firstOrFail();
        $paginator = $this->posts->paginate(12, ['category' => $slug]);
        return view('blog.category', compact('category', 'paginator'));
    }
}
