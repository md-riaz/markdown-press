<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class CodeHandler implements ShortcodeHandlerContract
{
    public function name(): string
    {
        return 'code';
    }

    public function render(array $attributes, ?string $content): string
    {
        $lang = e((string) ($attributes['lang'] ?? 'text'));
        $code = e((string) ($content ?? ''));

        return "<pre class=\"shortcode-code\"><code class=\"language-{$lang}\">{$code}</code></pre>";
    }
}
