<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class GistHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'gist'; }

    public function render(array $attributes, ?string $content): string
    {
        $id = $attributes['id'] ?? '';
        if (!$id) return '<!-- gist: missing id -->';

        $file = isset($attributes['file']) ? "?file={$attributes['file']}" : '';

        return <<<HTML
<div class="shortcode-gist">
  <script src="https://gist.github.com/{$id}.js{$file}"></script>
</div>
HTML;
    }
}
