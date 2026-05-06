<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class VideoHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'video'; }

    public function render(array $attributes, ?string $content): string
    {
        $src = $attributes['src'] ?? '';
        if (!$src) return '<!-- video: missing src -->';

        $type = $attributes['type'] ?? 'video/mp4';
        $poster = isset($attributes['poster']) ? "poster=\"{$attributes['poster']}\"" : '';

        return <<<HTML
<div class="shortcode-video">
  <video controls preload="metadata" style="width:100%;max-width:100%" {$poster}>
    <source src="{$src}" type="{$type}">
    Your browser does not support the video element.
  </video>
</div>
HTML;
    }
}
