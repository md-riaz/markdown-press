<?php

namespace App\Modules\Shortcode;

/**
 * Parses [shortcode attr="val"] and [shortcode]content[/shortcode] syntax.
 */
class ShortcodeParser
{
    private const PATTERN = '/\[([a-zA-Z_][a-zA-Z0-9_-]*)([^\]]*?)(?:\/)?\](?:(.*?)\[\/\1\])?/s';

    /**
     * @return array{name: string, attributes: array<string,string>, content: string|null, raw: string}[]
     */
    public function parse(string $text): array
    {
        $matches = [];
        preg_match_all(self::PATTERN, $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($found as $match) {
            $raw        = $match[0][0];
            $name       = $match[1][0];
            $attrString = trim($match[2][0]);
            $content    = isset($match[3]) ? $match[3][0] : null;

            $matches[] = [
                'name'       => $name,
                'attributes' => $this->parseAttributes($attrString),
                'content'    => $content !== '' ? $content : null,
                'raw'        => $raw,
            ];
        }

        return $matches;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $attrString): array
    {
        $attrs = [];
        // Matches: key="value", key='value', or key=value or bare key
        preg_match_all('/(\w[\w-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/', $attrString, $m, PREG_SET_ORDER);

        foreach ($m as $attr) {
            $key   = $attr[1];
            $value = $attr[2] !== '' ? $attr[2]
                   : ($attr[3] !== '' ? $attr[3]
                   : ($attr[4] !== '' ? $attr[4] : 'true'));
            $attrs[$key] = $value;
        }

        return $attrs;
    }
}
