<?php
namespace App\Modules\Shortcode\Handlers;
use App\Contracts\ShortcodeHandlerContract;

class CtaHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'cta'; }

    public function render(array $attributes, ?string $content): string
    {
        $url   = e($attributes['url'] ?? '#');
        $label = e($attributes['label'] ?? 'Learn More');
        $body  = $content ? "<p>" . e($content) . "</p>" : '';

        return <<<HTML
<div class="cta-block">
  {$body}
  <a href="{$url}" class="cta-button">{$label}</a>
</div>
HTML;
    }
}
