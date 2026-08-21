<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ColorAccent;
use App\Enums\ColorScheme;
use PHPUnit\Framework\TestCase;

class ColorSchemeTest extends TestCase
{
    public function test_default_scheme_is_dark_cyan(): void
    {
        $this->assertSame(ColorScheme::DarkCyan, ColorScheme::default());
    }

    public function test_from_parts_builds_the_matching_scheme(): void
    {
        $this->assertSame(
            ColorScheme::LightEmerald,
            ColorScheme::fromParts(false, ColorAccent::Emerald),
        );
    }

    public function test_accent_matches_the_scheme_suffix(): void
    {
        $this->assertSame(ColorAccent::Violet, ColorScheme::DarkViolet->accent());
    }

    public function test_dark_schemes_use_the_dark_html_class(): void
    {
        $this->assertSame('dark scroll-smooth', ColorScheme::DarkCyan->htmlClass());
    }

    public function test_light_schemes_omit_the_dark_html_class(): void
    {
        $this->assertSame('scroll-smooth', ColorScheme::LightCyan->htmlClass());
    }
}
