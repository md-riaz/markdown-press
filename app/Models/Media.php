<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'user_id','filename','disk','path','mime_type','size',
        'width','height','alt_text','is_starred','collection',
    ];

    protected $casts = ['is_starred' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function variants(): HasMany { return $this->hasMany(MediaVariant::class); }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function variant(string $name): ?MediaVariant
    {
        return $this->variants->firstWhere('variant_name', $name);
    }
}
