<?php

namespace Tests\Unit;

use App\Models\Box;
use App\Support\BoxTheme;
use PHPUnit\Framework\TestCase;

class BoxThemeTest extends TestCase
{
    public function test_theme_rejects_css_injection_and_selects_readable_button_text(): void
    {
        $box = new Box(['primary_color' => '#000000; background:url(https://example.com)', 'secondary_color' => '#123456']);
        $variables = BoxTheme::variables($box);
        $this->assertStringNotContainsString('url(', $variables);
        $this->assertStringContainsString('--box-primary:#C8FA3C;', $variables);
        $this->assertSame('#FFFFFF', BoxTheme::text('#000000'));
        $this->assertSame('#000000', BoxTheme::text('#FFFFFF'));
        $this->assertNotSame('#000000', BoxTheme::accent('#000000'));
    }
}
