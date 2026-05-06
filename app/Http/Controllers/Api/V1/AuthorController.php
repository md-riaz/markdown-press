<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AuthorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::select('id','name','username','bio','avatar_url')
                ->withCount('posts')->get()
        );
    }

    public function show(string $username): JsonResponse
    {
        $author = User::where('username', $username)
            ->select('id','name','username','bio','avatar_url')
            ->withCount('posts')
            ->firstOrFail();
        return response()->json($author);
    }
}
