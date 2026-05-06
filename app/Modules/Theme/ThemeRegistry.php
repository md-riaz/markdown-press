<?php

namespace App\Modules\Theme;

use App\Models\Theme;

class ThemeRegistry
{
    public function active(): ?Theme
    {
        return Theme::where('is_active', true)->first();
    }

    public function findBySlug(string $slug): ?Theme
    {
        return Theme::where('slug', $slug)->first();
    }

    public function activate(Theme $theme): void
    {
        Theme::where('is_active', true)->update(['is_active' => false]);
        $theme->update(['is_active' => true]);
    }

    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Theme::all();
    }
}
