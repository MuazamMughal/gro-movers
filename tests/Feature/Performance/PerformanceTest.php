<?php

namespace Tests\Feature\Performance;

use App\Services\Static\CriticalCss;
use App\Services\Static\StaticPublisher;
use App\Support\Fonts;
use App\Support\Theme;
use Database\Seeders\MenuSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE_CSS = <<<'CSS'
/*! tailwindcss */
@layer theme,base,components,utilities;
@layer theme{:root,:host{--color-tertiary:#f66419}}
@layer base{*,:after,:before{box-sizing:border-box}h1{margin:0}}
@layer utilities{.flex{display:flex}.hidden{display:none}.md\:pb-0{x:1}.text-tertiary-strong{color:var(--color-tertiary-strong)}.group-hover\:visible:is(:where(.group):hover *){visibility:visible}.unused-class{color:red}.a,.b{color:blue}.\[\&\:\:-webkit-details-marker\]\:hidden::-webkit-details-marker{display:none}}
@media (width>=48rem){.md\:pb-0{padding-bottom:0}.md\:grid{display:grid}}
@keyframes spin{to{transform:rotate(360deg)}}
@property --tw-x{syntax:"*";inherits:false}
.content-url:after{content:"a{b}c;"}
CSS;

    public function test_critical_css_keeps_only_rules_the_page_uses()
    {
        $html = '<div class="flex md:pb-0 group"><p class="text-tertiary-strong group-hover:visible b">x</p><summary class="[&amp;::-webkit-details-marker]:hidden"></summary></div>';

        $css = CriticalCss::extract($html, CriticalCss::parse(self::FIXTURE_CSS));

        foreach (['@layer theme,base,components,utilities;', ':root,:host{--color-tertiary:#f66419}', 'h1{margin:0}', '.flex{display:flex}', '.text-tertiary-strong{', '.group-hover\:visible:is(:where(.group):hover *){visibility:visible}', '@media (width>=48rem){.md\:pb-0{padding-bottom:0}}', '.b{color:blue}', '::-webkit-details-marker{display:none}', '@keyframes spin{to{transform:rotate(360deg)}}', '@property --tw-x{'] as $expected) {
            $this->assertStringContainsString($expected, $css);
        }

        foreach (['.hidden{', '.unused-class', '.md\:grid', '.a,', '.content-url'] as $unexpected) {
            $this->assertStringNotContainsString($unexpected, $css);
        }
    }

    public function test_critical_css_inlines_and_loads_the_full_sheet_without_blocking()
    {
        $file = public_path('build/assets/site-perftest.css');
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, self::FIXTURE_CSS);

        try {
            $html = '<head><link rel="preload" as="style" href="/build/assets/site-perftest.css" /><link rel="stylesheet" href="/build/assets/site-perftest.css" /></head><body class="flex"></body>';
            $out = CriticalCss::apply($html);

            $this->assertStringContainsString('<style data-critical>', $out);
            $this->assertStringContainsString('.flex{display:flex}', $out);
            $this->assertStringContainsString('<link rel="stylesheet" href="/build/assets/site-perftest.css" media="print" onload="this.media=\'all\'">', $out);
            $this->assertStringContainsString('<noscript><link rel="stylesheet" href="/build/assets/site-perftest.css"></noscript>', $out);
            $this->assertStringNotContainsString('rel="preload" as="style"', $out);

            // Pages without the site stylesheet are left alone.
            $this->assertSame('<p>no css</p>', CriticalCss::apply('<p>no css</p>'));
        } finally {
            @unlink($file);
            CriticalCss::flush();
        }
    }

    public function test_static_pages_get_critical_css_when_the_build_exists()
    {
        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MenuSeeder::class]);
        config(['static.path' => $root = storage_path('framework/testing/static-perf')]);

        $html = '<!DOCTYPE html><html><head><link rel="stylesheet" href="/build/assets/site-perftest.css" /></head><body class="flex">Hi</body></html>';
        $file = public_path('build/assets/site-perftest.css');
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, self::FIXTURE_CSS);

        try {
            $this->assertStringContainsString('data-critical', CriticalCss::apply($html));
            $this->assertSame('ok', app(StaticPublisher::class)->build('/about-us'));
        } finally {
            @unlink($file);
            File::deleteDirectory($root);
            CriticalCss::flush();
        }
    }

    public function test_only_woff2_font_faces_are_printed()
    {
        $css = '<style>@font-face { font-family: "Inter"; font-weight: 400; src: url("/a.woff2") format("woff2"); }'."\n"
            .'@font-face { font-family: "Inter"; font-weight: 400; src: url("/a.woff") format("woff"); }'."\n"
            .'@font-face { font-family: "Inter fallback"; src: local("Arial"); size-adjust: 108%; }</style>';

        $out = Fonts::withoutWoff($css);

        $this->assertStringContainsString('format("woff2")', $out);
        $this->assertStringNotContainsString('format("woff")', $out);
        $this->assertStringContainsString('Inter fallback', $out);
    }

    public function test_accent_shade_passes_wcag_aa_for_white_text()
    {
        $shade = Theme::accessibleShade('#F66419');

        $this->assertSame('#C55014', $shade);
        $this->assertGreaterThanOrEqual(4.5, Theme::contrast($shade, '#FFFFFF'));
        $this->assertSame('#0A2E5C', Theme::accessibleShade('#0A2E5C'), 'dark accents are used as they are');
        $this->assertGreaterThanOrEqual(4.5, Theme::contrast(Theme::accessibleShade('#FFD54F'), '#FFFFFF'), 'even a pale yellow gets a readable shade');

        $this->seed(SettingsSeeder::class);
        $this->assertArrayHasKey('--color-tertiary-strong', Theme::variables());
        $this->assertStringContainsString('--color-tertiary-strong:#C55014', Theme::css());
    }

    public function test_no_template_puts_white_text_on_the_raw_accent()
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($f) => preg_match('/\bbg-tertiary(?![\w\/-])[^"\']*\btext-white\b|\btext-white\b[^"\']*\bbg-tertiary(?![\w\/-])/', $f->getContents()))
            ->map(fn ($f) => $f->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Use bg-tertiary-strong behind white text (WCAG AA).');
    }

    public function test_media_has_a_small_variant_for_logos_and_avatars()
    {
        $this->assertContains(240, config('media.widths'));
    }
}
