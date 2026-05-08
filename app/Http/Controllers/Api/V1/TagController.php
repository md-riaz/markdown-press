<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Tag::withCount('posts')->get());
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json(Tag::where('slug', $slug)->withCount('posts')->firstOrFail());
    }
}
