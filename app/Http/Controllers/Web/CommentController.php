<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $request->validate([
            'body'        => 'required|string|max:2000',
            'guest_name'  => 'required_without:user_id|string|max:100',
            'guest_email' => 'required_without:user_id|email|max:255',
        ]);

        $post = Post::where('slug', $slug)->published()->firstOrFail();

        Comment::create([
            'post_id'     => $post->id,
            'body'        => $request->body,
            'guest_name'  => $request->guest_name,
            'guest_email' => $request->guest_email,
            'status'      => 'pending',
            'gravatar_hash' => $request->guest_email
                ? md5(strtolower(trim($request->guest_email)))
                : null,
        ]);

        return back()->with('success', 'Comment submitted and awaiting moderation.');
    }
}
