<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id', 'parent_id', 'user_id', 'guest_name', 'guest_email',
        'guest_country', 'body', 'status', 'gravatar_hash',
    ];

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
    public function parent(): BelongsTo { return $this->belongsTo(Comment::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(Comment::class, 'parent_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Anonymous';
    }
}
