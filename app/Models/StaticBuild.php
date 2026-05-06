<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StaticBuild extends Model
{
    protected $fillable = [
        'theme_id', 'status', 'file_count', 'image_count',
        'zip_size', 'zip_path', 'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function theme(): BelongsTo { return $this->belongsTo(Theme::class); }

    public function getZipUrlAttribute(): ?string
    {
        return $this->zip_path ? Storage::url($this->zip_path) : null;
    }
}
