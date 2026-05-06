<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class CodepenHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'codepen'; }

    public function render(array $attributes, ?string $content): string
    {
        $slug = $attributes['slug'] ?? '';
        $user = $attributes['user'] ?? '';
        if (!$slug || !$user) return '<!-- codepen: missing slug or user -->';

        $height = $attributes['height'] ?? '400';
        $tab    = $attributes['tab'] ?? 'result';

        return <<<HTML
<div class="shortcode-codepen">
  <iframe height="{$height}" style="width:100%;" scrolling="no"
          src="https://codepen.io/{$user}/embed/{$slug}?default-tab={$tab}"
          frameborder="no" loading="lazy" allowfullscreen></iframe>
</div>
HTML;
    }
}
