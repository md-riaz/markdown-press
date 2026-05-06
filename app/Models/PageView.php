<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'ip_hash', 'country', 'session_id', 'viewed_at'];
    protected $casts = ['viewed_at' => 'datetime'];

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
}
