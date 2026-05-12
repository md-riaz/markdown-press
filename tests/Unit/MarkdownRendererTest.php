<?php
namespace Tests\Unit;

use App\Modules\Post\MarkdownRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    use RefreshDatabase;

    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = app(MarkdownRenderer::class);
    }

    public function test_it_renders_basic_markdown(): void
    {
        $html = $this->renderer->toHtml('# Hello');
        $this->assertStringContainsString('<h1>Hello</h1>', $html);
    }

    public function test_it_strips_script_tags(): void
    {
        $html = $this->renderer->toHtml('Hello <script>alert("xss")</script> world');
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_it_renders_gfm_table(): void
    {
        $md   = "| A | B |\n|---|---|\n| 1 | 2 |";
        $html = $this->renderer->toHtml($md);
        $this->assertStringContainsString('<table>', $html);
    }
}
