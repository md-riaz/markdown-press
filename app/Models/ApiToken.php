<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token', 'abilities', 'ip_whitelist', 'last_used_at', 'expires_at'];

    protected $casts = [
        'abilities'    => 'array',
        'ip_whitelist' => 'array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    protected $hidden = ['token'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAllowedFromIp(string $ip): bool
    {
        if (empty($this->ip_whitelist)) return true;
        return in_array($ip, $this->ip_whitelist);
    }
}
