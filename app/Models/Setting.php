<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$group}.{$key}", 3600, function () use ($group, $key, $default) {
            $s = static::where('group', $group)->where('key', $key)->first();
            return $s ? $s->value : $default;
        });
    }

    public static function set(string $group, string $key, mixed $value): void
    {
        static::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
        Cache::forget("setting:{$group}.{$key}");
    }
}
