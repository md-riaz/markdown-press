<?php
namespace App\Modules\Shortcode\Handlers;
use App\Contracts\ShortcodeHandlerContract;
use App\Models\Media;

class GalleryHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'gallery'; }

    public function render(array $attributes, ?string $content): string
    {
        $ids = array_filter(array_map('trim', explode(',', $attributes['ids'] ?? '')));
        if (empty($ids)) return '<!-- gallery: no ids provided -->';

        $items = '';
        $mediaItems = Media::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $media = $mediaItems->get($id);
            if (!$media) continue;
            $url = $media->url;
            $alt = e($media->alt_text ?? '');
            $items .= "<div class=\"gallery-item\"><img src=\"{$url}\" alt=\"{$alt}\" loading=\"lazy\"></div>";
        }

        return "<div class=\"gallery-grid\">{$items}</div>";
    }
}
