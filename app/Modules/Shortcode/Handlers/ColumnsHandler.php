<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class ColumnsHandler implements ShortcodeHandlerContract
{
    public function name(): string
    {
        return 'columns';
    }

    public function render(array $attributes, ?string $content): string
    {
        $parts = collect(explode('||', (string) ($content ?? '')))
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return '<!-- columns: empty content -->';
        }

        $columns = $parts->map(
            fn (string $part) => '<div class="column">'.e($part).'</div>'
        )->implode('');

        return "<div class=\"columns\">{$columns}</div>";
    }
}
