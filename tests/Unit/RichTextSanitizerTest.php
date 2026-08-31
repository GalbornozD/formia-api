<?php

namespace Tests\Unit;

use App\Casts\SanitizedRichText;
use PHPUnit\Framework\TestCase;

class RichTextSanitizerTest extends TestCase
{
    public function test_it_keeps_supported_semantic_html(): void
    {
        $html = '<h1 class="ql-align-center" style="color:red">Título</h1>'
            .'<p>Texto <strong>importante</strong> y <em>claro</em>.</p>'
            .'<ul><li>Elemento</li></ul>';

        $sanitized = (new SanitizedRichText)->sanitize($html);

        $this->assertStringContainsString('<h1 class="ql-align-center">Título</h1>', $sanitized);
        $this->assertStringContainsString('<strong>importante</strong>', $sanitized);
        $this->assertStringContainsString('<ul><li>Elemento</li></ul>', $sanitized);
        $this->assertStringNotContainsString('style=', $sanitized);
    }

    public function test_it_removes_dangerous_markup_and_link_protocols(): void
    {
        $html = '<p onclick="alert(1)">Seguro</p>'
            .'<script>alert(2)</script><iframe src="https://example.com"></iframe>'
            .'<a href="javascript:alert(3)" target="_blank">Enlace</a>';

        $sanitized = (new SanitizedRichText)->sanitize($html);

        $this->assertSame('<p>Seguro</p><a>Enlace</a>', $sanitized);
    }

    public function test_it_protects_links_opened_in_a_new_tab(): void
    {
        $sanitized = (new SanitizedRichText)->sanitize(
            '<a href="https://formia.cl" target="_blank" onclick="alert(1)">FormIA</a>',
        );

        $this->assertSame(
            '<a href="https://formia.cl" target="_blank" rel="noopener noreferrer">FormIA</a>',
            $sanitized,
        );
    }

    public function test_it_normalizes_visually_empty_content_to_null(): void
    {
        $sanitizer = new SanitizedRichText;

        $this->assertNull($sanitizer->sanitize(null));
        $this->assertNull($sanitizer->sanitize(''));
        $this->assertNull($sanitizer->sanitize('<p><br></p>'));
        $this->assertSame('Descripción simple', $sanitizer->sanitize('Descripción simple'));
    }
}
