<?php
namespace Tests\Unit;

use App\Modules\Shortcode\ShortcodeParser;
use Tests\TestCase;

class ShortcodeParserTest extends TestCase
{
    private ShortcodeParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ShortcodeParser();
    }

    public function test_it_parses_self_closing_shortcode(): void
    {
        $result = $this->parser->parse('[youtube id="abc"]');
        $this->assertCount(1, $result);
        $this->assertSame('youtube', $result[0]['name']);
        $this->assertSame('abc', $result[0]['attributes']['id']);
    }

    public function test_it_parses_block_shortcode(): void
    {
        $result = $this->parser->parse('[notice type="info"]Hello[/notice]');
        $this->assertCount(1, $result);
        $this->assertSame('Hello', $result[0]['content']);
    }

    public function test_it_parses_multiple_shortcodes(): void
    {
        $result = $this->parser->parse('[youtube id="abc"] some text [gallery ids="1,2,3"]');
        $this->assertCount(2, $result);
    }

    public function test_it_returns_empty_array_for_plain_text(): void
    {
        $result = $this->parser->parse('Just plain text with no shortcodes.');
        $this->assertCount(0, $result);
    }
}
