<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate(['email' => 'required|email|max:255']);

        Subscriber::firstOrCreate(
            ['email' => $request->email],
            [
                'name'          => $request->name,
                'token'         => Str::random(64),
                'status'        => 'active',
                'subscribed_at' => now(),
            ]
        );

        return back()->with('success', 'Subscribed! Check your email.');
    }

    public function unsubscribe(string $token)
    {
        $sub = Subscriber::where('token', $token)->firstOrFail();
        $sub->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        return view('newsletter.unsubscribed');
    }
}
