<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class NoticeHandler implements ShortcodeHandlerContract
{
    public function name(): string
    {
        return 'notice';
    }

    public function render(array $attributes, ?string $content): string
    {
        $type = (string) ($attributes['type'] ?? 'info');
        $allowed = ['info', 'warning', 'error', 'success'];
        $type = in_array($type, $allowed, true) ? $type : 'info';
        $body = e((string) ($content ?? ''));

        return "<div class=\"notice notice-{$type}\">{$body}</div>";
    }
}
