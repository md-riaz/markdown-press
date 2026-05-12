<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\NewsletterService;
use Illuminate\Http\Request;

class SubscribeController extends Controller
{
    public function __construct(private NewsletterService $newsletter) {}

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $this->newsletter->subscribe($validated['email'], $validated['name'] ?? null);

        return back()->with('success', 'Subscription updated successfully.');
    }

    public function unsubscribe(string $token)
    {
        $this->newsletter->unsubscribe($token);

        return view('newsletter.unsubscribed');
    }
}
