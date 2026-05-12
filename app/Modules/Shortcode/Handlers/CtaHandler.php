<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class CtaHandler implements ShortcodeHandlerContract
{
    public function name(): string
    {
        return 'cta';
    }

    public function render(array $attributes, ?string $content): string
    {
        $url = e((string) ($attributes['url'] ?? '#'));
        $label = e((string) ($attributes['label'] ?? 'Learn more'));
        $body = $content ? '<p>'.e($content).'</p>' : '';

        return <<<HTML
<div class="shortcode-cta cta-block">
  {$body}
  <a href="{$url}" class="cta-button">{$label}</a>
</div>
HTML;
    }
}
