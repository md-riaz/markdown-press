<?php

namespace App\Services;

use App\Mail\NewsletterSubscriptionConfirmed;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;

class NewsletterService
{
    public function subscribe(string $email, ?string $name = null): Subscriber
    {
        $subscriber = Subscriber::firstOrNew([
            'email' => Subscriber::normalizeEmail($email),
        ]);
        $shouldResetSubscriptionDate = ! $subscriber->exists || $subscriber->status === 'unsubscribed';

        $subscriber->fill([
            'name' => $name ?: $subscriber->name,
            'status' => 'active',
            'token' => $subscriber->token ?: Subscriber::generateUniqueToken(),
            'subscribed_at' => $shouldResetSubscriptionDate ? now() : $subscriber->subscribed_at,
            'unsubscribed_at' => null,
        ]);

        $subscriber->save();

        Mail::to($subscriber->email, $subscriber->name)->send(new NewsletterSubscriptionConfirmed($subscriber));

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
        $subscriber = Subscriber::where('email', Subscriber::normalizeEmail($email))->firstOrFail();

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
