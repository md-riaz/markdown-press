<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function __construct(private NewsletterService $newsletter) {}

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $existing = Subscriber::where('email', strtolower($validated['email']))->exists();
        $subscriber = $this->newsletter->subscribe($validated['email'], $validated['name'] ?? null);

        return response()->json([
            'data' => $this->resource($subscriber),
        ], $existing ? 200 : 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = $this->newsletter->unsubscribeByEmail($validated['email']);

        return response()->json([
            'data' => $this->resource($subscriber),
        ]);
    }

    private function resource(Subscriber $subscriber): array
    {
        return [
            'id' => $subscriber->id,
            'name' => $subscriber->name,
            'email' => $subscriber->email,
            'status' => $subscriber->status,
            'subscribed_at' => $subscriber->subscribed_at?->toIso8601String(),
            'unsubscribed_at' => $subscriber->unsubscribed_at?->toIso8601String(),
        ];
    }
}
