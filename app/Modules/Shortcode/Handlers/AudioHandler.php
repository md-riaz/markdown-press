<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class AudioHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'audio'; }

    public function render(array $attributes, ?string $content): string
    {
        $src = $attributes['src'] ?? '';
        if (!$src) return '<!-- audio: missing src -->';

        $type = $attributes['type'] ?? 'audio/mpeg';

        return <<<HTML
<div class="shortcode-audio">
  <audio controls preload="metadata" style="width:100%">
    <source src="{$src}" type="{$type}">
    Your browser does not support the audio element.
  </audio>
</div>
HTML;
    }
}
