<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class MermaidHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'mermaid'; }

    public function render(array $attributes, ?string $content): string
    {
        $diagram = htmlspecialchars(trim($content ?? ''), ENT_QUOTES, 'UTF-8');
        return <<<HTML
<div class="shortcode-mermaid mermaid">{$diagram}</div>
HTML;
    }
}
