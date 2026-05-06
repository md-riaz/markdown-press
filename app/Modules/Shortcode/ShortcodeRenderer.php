<?php

namespace App\Modules\Shortcode;

class ShortcodeRenderer
{
    public function __construct(
        private ShortcodeParser   $parser,
        private ShortcodeRegistry $registry,
    ) {}

    /**
     * Replace all recognised shortcodes in $text with rendered HTML.
     */
    public function render(string $text): string
    {
        $tokens = $this->parser->parse($text);

        foreach ($tokens as $token) {
            if (!$this->registry->has($token['name'])) {
                continue;
            }

            $handler = $this->registry->get($token['name']);
            $html    = $handler->render($token['attributes'], $token['content']);
            $text    = str_replace($token['raw'], $html, $text);
        }

        return $text;
    }
}
