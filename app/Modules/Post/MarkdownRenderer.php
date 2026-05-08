<?php

namespace App\Modules\Post;

use App\Modules\Shortcode\ShortcodeRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct(private ShortcodeRenderer $shortcodes)
    {
        $env = new Environment([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => 100,
        ]);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new AutolinkExtension());

        $this->converter = new MarkdownConverter($env);
    }

    /**
     * Convert Markdown (including shortcodes) to HTML.
     */
    public function toHtml(string $markdown): string
    {
        // 1. Extract & render shortcodes first
        $withShortcodes = $this->shortcodes->render($markdown);

        // 2. Pass through CommonMark
        return (string) $this->converter->convert($withShortcodes);
    }
}
