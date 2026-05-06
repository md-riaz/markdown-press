<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class AlertHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'alert'; }

    public function render(array $attributes, ?string $content): string
    {
        $type  = in_array($attributes['type'] ?? 'info', ['info','success','warning','danger'])
               ? ($attributes['type'] ?? 'info') : 'info';
        $title = $attributes['title'] ?? '';
        $body  = $content ?? '';

        $titleHtml = $title ? "<strong class=\"alert-title\">{$title}</strong>" : '';

        return <<<HTML
<div class="shortcode-alert alert alert-{$type}" role="alert">
  {$titleHtml}
  <p>{$body}</p>
</div>
HTML;
    }
}
