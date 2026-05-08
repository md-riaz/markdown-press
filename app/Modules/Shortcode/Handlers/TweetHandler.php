<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class TweetHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'tweet'; }

    public function render(array $attributes, ?string $content): string
    {
        $id = $attributes['id'] ?? '';
        if (!$id) return '<!-- tweet: missing id -->';

        return <<<HTML
<div class="shortcode-tweet" data-tweet-id="{$id}">
  <blockquote class="twitter-tweet">
    <a href="https://twitter.com/i/web/status/{$id}">Loading tweet...</a>
  </blockquote>
  <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
</div>
HTML;
    }
}
