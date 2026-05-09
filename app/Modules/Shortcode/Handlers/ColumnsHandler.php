<?php
namespace App\Modules\Shortcode\Handlers;
use App\Contracts\ShortcodeHandlerContract;

class ColumnsHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'columns'; }

    public function render(array $attributes, ?string $content): string
    {
        $parts = explode('||', $content ?? '', 2);
        $col1  = trim($parts[0] ?? '');
        $col2  = trim($parts[1] ?? '');

        return <<<HTML
<div class="columns">
  <div class="column">{$col1}</div>
  <div class="column">{$col2}</div>
</div>
HTML;
    }
}
