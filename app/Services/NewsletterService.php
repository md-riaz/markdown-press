<?php

namespace App\Services;

use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterService
{
    public function subscribe(string $email, ?string $name = null): Subscriber
    {
        $subscriber = Subscriber::firstOrNew([
            'email' => Str::lower(trim($email)),
        ]);

        $subscriber->fill([
            'name' => $name ?: $subscriber->name,
            'status' => 'active',
            'token' => $subscriber->token ?: Subscriber::generateUniqueToken(),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        $subscriber->save();

        Mail::raw(
            "You're subscribed to the MarkdownPress newsletter.\n\nUnsubscribe: ".route('newsletter.unsubscribe', $subscriber->token),
            function ($message) use ($subscriber): void {
                $message
                    ->to($subscriber->email, $subscriber->name)
                    ->subject('Newsletter subscription confirmed');
            }
        );

        return $subscriber->fresh();
    }

    public function unsubscribe(string $token): Subscriber
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return $subscriber->fresh();
    }

    public function unsubscribeByEmail(string $email): Subscriber
    {
        $subscriber = Subscriber::where('email', Str::lower(trim($email)))->firstOrFail();

        if ($subscriber->status !== 'unsubscribed') {
            $subscriber->update([
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);
        }

        return $subscriber->fresh();
    }

    public function import(array $subscribers): array
    {
        $result = ['imported' => 0, 'skipped' => 0];

        foreach ($subscribers as $subscriber) {
            $email = $subscriber['email'] ?? null;

            if (! $email) {
                $result['skipped']++;

                continue;
            }

            $this->subscribe($email, $subscriber['name'] ?? null);
            $result['imported']++;
        }

        return $result;
    }
}
