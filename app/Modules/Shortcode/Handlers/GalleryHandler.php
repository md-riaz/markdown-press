<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;
use App\Models\Media;

class GalleryHandler implements ShortcodeHandlerContract
{
    public function name(): string
    {
        return 'gallery';
    }

    public function render(array $attributes, ?string $content): string
    {
        $ids = collect(explode(',', (string) ($attributes['ids'] ?? '')))
            ->map(fn (string $id) => (int) trim($id))
            ->filter(fn (int $id) => $id > 0)
            ->values();

        if ($ids->isEmpty()) {
            return '<!-- gallery: missing ids -->';
        }

        $media = Media::whereIn('id', $ids)->get()->keyBy('id');

        $items = $ids->map(function (int $id) use ($media) {
            $item = $media->get($id);
            if (! $item) {
                return '';
            }

            $url = e($item->url);
            $alt = e($item->alt_text ?: $item->filename);

            return "<figure class=\"gallery-item\"><img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\"></figure>";
        })->filter()->implode('');

        if ($items === '') {
            return '<!-- gallery: no valid media -->';
        }

        return "<div class=\"shortcode-gallery gallery-grid\">{$items}</div>";
    }
}
