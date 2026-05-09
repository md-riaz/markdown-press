<?php
namespace App\Modules\Shortcode\Handlers;
use App\Contracts\ShortcodeHandlerContract;

class CodeHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'code'; }

    public function render(array $attributes, ?string $content): string
    {
        $lang = e($attributes['lang'] ?? 'text');
        $code = e($content ?? '');
        return "<pre><code class=\"language-{$lang}\">{$code}</code></pre>";
    }
}
