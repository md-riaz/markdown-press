<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            $subscriber->email = Str::lower(trim($subscriber->email));
            $subscriber->token ??= static::generateUniqueToken();
            static::syncLifecycleState($subscriber);
        });

        static::saving(function (Subscriber $subscriber): void {
            $subscriber->email = Str::lower(trim($subscriber->email));
            static::syncLifecycleState($subscriber);
        });
    }

    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('token', $token)->exists());

        return $token;
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
