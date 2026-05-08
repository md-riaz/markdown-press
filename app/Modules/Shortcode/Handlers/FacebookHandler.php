<?php

namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;

class FacebookHandler implements ShortcodeHandlerContract
{
    public function name(): string { return 'facebook'; }

    public function render(array $attributes, ?string $content): string
    {
        $url = $attributes['url'] ?? '';
        if (!$url) return '<!-- facebook: missing url -->';
        $encoded = htmlspecialchars($url, ENT_QUOTES);

        return <<<HTML
<div class="shortcode-facebook">
  <div class="fb-post" data-href="{$encoded}" data-width="500"></div>
  <div id="fb-root"></div>
  <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0"></script>
</div>
HTML;
    }
}
