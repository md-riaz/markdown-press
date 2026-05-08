<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeCustomization extends Model
{
    protected $fillable = ['theme_id', 'key', 'value'];

    public function theme(): BelongsTo { return $this->belongsTo(Theme::class); }
}
