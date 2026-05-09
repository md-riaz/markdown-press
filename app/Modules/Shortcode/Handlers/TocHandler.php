<?php
namespace App\Modules\Shortcode\Handlers;
use App\Contracts\ShortcodeHandlerContract;

class TocHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'toc'; }

    public function render(array $attributes, ?string $content): string
    {
        return '<nav id="toc" class="toc"><!-- Table of Contents --></nav>';
    }
}
