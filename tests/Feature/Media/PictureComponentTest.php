<?php

namespace Tests\Feature\Media;

use App\Services\Media\MediaService;
use App\View\Components\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PictureComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Picture::flushCache();
    }

    public function test_renders_webp_sources_fallback_dimensions_and_lazy_loading()
    {
        $media = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 2000, 1000), 'card', null, ['alt' => 'Movers carrying a sofa']);

        $html = Blade::render('<x-picture :media="$m" sizes="(min-width: 768px) 33vw, 100vw" class="rounded-xl" />', ['m' => $media->id]);

        $this->assertStringContainsString('<picture class="rounded-xl">', $html);
        $this->assertMatchesRegularExpression('#<source type="image/webp" srcset="/storage/media/[^"]+-480\.webp 480w, /storage/media/[^"]+-600\.webp 600w"#', $html);
        $this->assertStringContainsString('sizes="(min-width: 768px) 33vw, 100vw"', $html);
        $this->assertMatchesRegularExpression('#src="/storage/media/[^"]+-600\.jpg"#', $html);
        $this->assertStringContainsString('alt="Movers carrying a sofa"', $html);
        $this->assertStringContainsString('width="600"', $html);
        $this->assertStringContainsString('height="400"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_eager_images_get_high_fetch_priority()
    {
        $media = app(MediaService::class)->upload(UploadedFile::fake()->image('hero.jpg', 1920, 800), 'hero');

        $html = Blade::render('<x-picture :media="$m" eager />', ['m' => $media]);

        $this->assertStringContainsString('loading="eager" fetchpriority="high"', $html);
    }

    public function test_svg_renders_a_plain_image_and_missing_media_renders_nothing()
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 50"/>');
        $media = app(MediaService::class)->upload($svg, 'logo');

        $html = Blade::render('<x-picture :media="$m" />', ['m' => $media]);
        $this->assertStringNotContainsString('<source', $html);
        $this->assertMatchesRegularExpression('#src="/storage/media/[^"]+\.svg"#', $html);
        $this->assertStringContainsString('width="200" height="50"', preg_replace('/\s+/', ' ', $html));

        $this->assertSame('', trim(Blade::render('<x-picture :media="null" />')));
        $this->assertSame('', trim(Blade::render('<x-picture :media="999" />')));
    }
}
