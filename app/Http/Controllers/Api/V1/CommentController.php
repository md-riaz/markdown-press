<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function approve(int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $comment->update(['status' => 'approved']);
        return response()->json($comment);
    }

    public function reject(int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $comment->update(['status' => 'rejected']);
        return response()->json($comment);
    }

    public function destroy(int $id): JsonResponse
    {
        Comment::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
