<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

class Subscriber extends Model
{
    protected $fillable = ['name', 'email', 'status', 'token', 'subscribed_at', 'unsubscribed_at'];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber): void {
            $subscriber->email = static::normalizeEmail($subscriber->email);
            $subscriber->token ??= static::generateUniqueToken();
            static::syncLifecycleState($subscriber);
        });

        static::saving(function (Subscriber $subscriber): void {
            $subscriber->email = static::normalizeEmail($subscriber->email);
            static::syncLifecycleState($subscriber);
        });
    }

    public static function generateUniqueToken(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = Str::random(64);

            if (! static::where('token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Unable to generate a unique subscriber token.');
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private static function syncLifecycleState(Subscriber $subscriber): void
    {
        if ($subscriber->status === 'unsubscribed') {
            $subscriber->unsubscribed_at ??= now();

            return;
        }

        $subscriber->subscribed_at ??= now();
        $subscriber->unsubscribed_at = null;
    }
}
