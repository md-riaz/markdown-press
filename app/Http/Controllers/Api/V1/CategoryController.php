<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Category::withCount('posts')->orderBy('sort_order')->get());
    }

    public function show(string $slug): JsonResponse
    {
        $cat = Category::where('slug', $slug)->withCount('posts')->firstOrFail();
        return response()->json($cat);
    }
}
