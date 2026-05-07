<?php

namespace App\Modules\Theme;

use App\Models\Theme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ThemeRegistry
{
    /**
     * Discover themes from theme manifest files and upsert them into the database.
     */
    public function discover(): Collection
    {
        $themesPath = config('theme.themes_path', resource_path('themes'));

        if (! File::isDirectory($themesPath)) {
            return collect();
        }

        return collect(File::directories($themesPath))
            ->filter(fn (string $dir) => File::isFile($dir.'/theme.json'))
            ->map(fn (string $dir) => $this->upsertFromDirectory($dir));
    }

    public function active(): ?Theme
    {
        return Theme::where('is_active', true)->first()
            ?? Theme::where('slug', config('theme.default_theme', 'hello-world'))->first()
            ?? Theme::first();
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

    private function upsertFromDirectory(string $dir): Theme
    {
        $manifest = json_decode(File::get($dir.'/theme.json'), true);

        if (! is_array($manifest)) {
            throw new InvalidArgumentException("Invalid theme manifest in [{$dir}].");
        }

        $this->validateManifest($manifest, $dir);

        $slug = $manifest['key'];

        return Theme::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $manifest['name'],
                'description' => $manifest['description'] ?? null,
                'thumbnail_path' => File::isFile($dir.'/screenshot.png')
                    ? str_replace(resource_path().DIRECTORY_SEPARATOR, '', $dir.'/screenshot.png')
                    : null,
                'config' => $manifest,
            ]
        );
    }

    private function validateManifest(array $manifest, string $dir): void
    {
        foreach (['key', 'name', 'author', 'version'] as $field) {
            if (! isset($manifest[$field]) || ! is_string($manifest[$field]) || trim($manifest[$field]) === '') {
                throw new InvalidArgumentException("Theme [{$dir}] is missing required [{$field}] in theme.json.");
            }
        }
    }
}
