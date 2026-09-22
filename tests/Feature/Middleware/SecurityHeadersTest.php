<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private function csp(): string
    {
        return (string) $this->get('/')->headers->get('Content-Security-Policy');
    }

    public function test_core_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * brand.css pulls Space Grotesk and Inter via @import, which the browser
     * treats as a stylesheet load (style-src) that then fetches font files
     * from a second origin (font-src). Both origins were missing, so the
     * fonts were blocked and every page silently fell back to system faces.
     */
    public function test_the_csp_allows_the_google_fonts_used_by_brand_css(): void
    {
        $csp = $this->csp();

        $styleSrc = $this->directive($csp, 'style-src');
        $fontSrc = $this->directive($csp, 'font-src');

        $this->assertStringContainsString('https://fonts.googleapis.com', $styleSrc);
        $this->assertStringContainsString('https://fonts.gstatic.com', $fontSrc);
    }

    public function test_the_csp_still_allows_the_bootstrap_and_font_awesome_cdns(): void
    {
        $csp = $this->csp();

        foreach (['script-src', 'style-src'] as $name) {
            $directive = $this->directive($csp, $name);
            $this->assertStringContainsString('https://cdn.jsdelivr.net', $directive);
            $this->assertStringContainsString('https://cdnjs.cloudflare.com', $directive);
        }
    }

    private function directive(string $csp, string $name): string
    {
        foreach (explode(';', $csp) as $part) {
            $part = trim($part);
            if (str_starts_with($part, $name . ' ')) {
                return $part;
            }
        }

        $this->fail("CSP directive [{$name}] is missing entirely.");
    }
}
