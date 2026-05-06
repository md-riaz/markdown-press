<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model
{
    protected $fillable = [
        'post_id', 'locale', 'title', 'slug',
        'content_markdown', 'content_html_cached',
        'meta_title', 'meta_description', 'is_ai_translated',
    ];

    protected $casts = ['is_ai_translated' => 'boolean'];

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
}
