<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'slug', 'description', 'thumbnail_path', 'config', 'is_active', 'is_default'];
    protected $casts = ['config' => 'array', 'is_active' => 'boolean', 'is_default' => 'boolean'];

    public function customizations(): HasMany { return $this->hasMany(ThemeCustomization::class); }
    public function staticBuilds(): HasMany { return $this->hasMany(StaticBuild::class); }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
