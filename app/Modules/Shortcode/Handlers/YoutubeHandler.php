<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class YoutubeHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'youtube'; }

    public function render(array $attributes, ?string $content): string
    {
        $id = $attributes['id'] ?? ($attributes[0] ?? '');
        if (!$id) return '<!-- youtube: missing id -->';

        $width  = $attributes['width'] ?? '100%';
        $height = $attributes['height'] ?? '400';

        return <<<HTML
<div class="shortcode-youtube" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">
  <iframe src="https://www.youtube-nocookie.com/embed/{$id}"
          style="position:absolute;top:0;left:0;width:100%;height:100%;"
          frameborder="0" allowfullscreen loading="lazy"
          title="YouTube video player"></iframe>
</div>
HTML;
    }
}
