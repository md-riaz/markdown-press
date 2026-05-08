<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortcodeRegistry extends Model
{
    public $timestamps = false;
    protected $table = 'shortcode_registry';
    protected $fillable = ['name', 'handler_class', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
