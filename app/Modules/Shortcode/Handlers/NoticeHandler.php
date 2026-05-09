<?php
namespace App\Modules\Shortcode\Handlers;
use App\Contracts\ShortcodeHandlerContract;

class NoticeHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'notice'; }

    public function render(array $attributes, ?string $content): string
    {
        $type    = in_array($attributes['type'] ?? '', ['info','warning','error','success'])
                   ? $attributes['type'] : 'info';
        $inner   = $content ?? '';
        return "<div class=\"notice notice-{$type}\">{$inner}</div>";
    }
}
