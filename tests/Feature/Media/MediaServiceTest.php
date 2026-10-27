<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->service = app(MediaService::class);
    }

    public function test_hero_upload_without_crop_is_center_cropped_into_all_widths()
    {
        $media = $this->service->upload(UploadedFile::fake()->image('Moving_Truck-Dallas.jpg', 2400, 1600), 'hero');

        $this->assertSame([2400, 1600], [$media->width, $media->height]);
        $this->assertSame('Moving Truck Dallas', $media->alt);
        $this->assertSame(['x' => 0, 'y' => 300, 'w' => 2400, 'h' => 1000, 'rotate' => 0], $media->crop);
        $this->assertSame([240, 480, 768, 1200, 1920], $media->variants->where('format', 'webp')->pluck('width')->values()->all());
        $this->assertSame([240, 480, 768, 1200, 1920], $media->variants->where('format', 'jpg')->pluck('width')->values()->all());
        $this->assertSame([1920, 800], $media->displaySize());

        foreach ($media->variants as $variant) {
            Storage::disk('public')->assertExists($variant->path);
        }
        Storage::disk('public')->assertExists($media->original_path);

        $largest = getimagesize(Storage::disk('public')->path($media->variants->where('format', 'webp')->last()->path));
        $this->assertSame([1920, 800, 'image/webp'], [$largest[0], $largest[1], $largest['mime']]);
    }

    public function test_explicit_crop_box_is_used_and_clamped()
    {
        $media = $this->service->upload(UploadedFile::fake()->image('a.jpg', 1000, 1000), 'card', ['x' => 100, 'y' => 100, 'w' => 600, 'h' => 400]);

        $this->assertSame(['x' => 100, 'y' => 100, 'w' => 600, 'h' => 400, 'rotate' => 0], $media->crop);
        $this->assertSame([600, 400], $media->displaySize());
        $this->assertSame([240, 480, 600], $media->variants->where('format', 'webp')->pluck('width')->values()->all());

        $clamped = $this->service->upload(UploadedFile::fake()->image('b.jpg', 500, 500), 'square', ['x' => 400, 'y' => 0, 'w' => 900, 'h' => 900]);
        $this->assertSame(['x' => 400, 'y' => 0, 'w' => 100, 'h' => 500, 'rotate' => 0], $clamped->crop);
    }

    public function test_small_images_are_never_upscaled()
    {
        $media = $this->service->upload(UploadedFile::fake()->image('small.jpg', 800, 400), 'hero');

        $this->assertSame([240, 480, 768, 800], $media->variants->where('format', 'webp')->pluck('width')->values()->all());
        $this->assertSame([800, 333], $media->displaySize());
    }

    public function test_png_keeps_a_png_fallback_and_jpg_gets_jpg()
    {
        $png = $this->service->upload(UploadedFile::fake()->image('logo.png', 900, 300), 'logo');
        $jpg = $this->service->upload(UploadedFile::fake()->image('photo.jpg', 900, 600), 'card');

        $this->assertSame('png', $png->fallbackFormat());
        $this->assertSame('jpg', $jpg->fallbackFormat());
        $this->assertSame([900, 300], $png->displaySize(), 'logo is free crop and keeps its aspect');
    }

    public function test_rotation_is_clockwise_before_cropping()
    {
        // Left half red, right half blue.
        $path = tempnam(sys_get_temp_dir(), 'rot').'.png';
        $img = imagecreatetruecolor(400, 200);
        imagefilledrectangle($img, 0, 0, 199, 199, imagecolorallocate($img, 255, 0, 0));
        imagefilledrectangle($img, 200, 0, 399, 199, imagecolorallocate($img, 0, 0, 255));
        imagepng($img, $path);

        $media = $this->service->upload(new UploadedFile($path, 'rot.png', 'image/png', null, true), 'logo', ['x' => 0, 'y' => 0, 'w' => 200, 'h' => 400, 'rotate' => 90]);

        $this->assertSame([200, 400], $media->displaySize());

        $out = imagecreatefrompng(Storage::disk('public')->path($media->variants->firstWhere('format', 'png')->path));
        $top = imagecolorsforindex($out, imagecolorat($out, 100, 20));
        // Rotating clockwise moves the left (red) half to the top.
        $this->assertGreaterThan(200, $top['red']);
        $this->assertLessThan(60, $top['blue']);
    }

    public function test_svg_logos_are_sanitized_and_have_no_variants()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 100"><script>alert(1)</script><rect width="300" height="100" onload="alert(2)"/></svg>';
        $media = $this->service->upload(UploadedFile::fake()->createWithContent('logo.svg', $svg), 'logo');

        $stored = Storage::disk('public')->get($media->original_path);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertSame([300, 100], [$media->width, $media->height]);
        $this->assertCount(0, $media->variants);
        $this->assertSame($media->originalUrl(), $media->url());
    }

    public function test_recrop_rebuilds_variants_and_removes_old_files()
    {
        $media = $this->service->upload(UploadedFile::fake()->image('a.jpg', 1600, 1200), 'card');
        $oldPaths = $media->variants->pluck('path')->all();

        $media = $this->service->recrop($media, 'square', ['x' => 0, 'y' => 0, 'w' => 1200, 'h' => 1200]);

        $this->assertSame('square', $media->preset);
        $this->assertSame([600, 600], $media->displaySize());
        foreach ($oldPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_derive_creates_an_independent_copy()
    {
        $source = $this->service->upload(UploadedFile::fake()->image('a.jpg', 2000, 1200), 'hero');
        $copy = $this->service->derive($source, 'card', null);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertNotSame($source->original_path, $copy->original_path);
        $this->assertSame('hero', $source->fresh()->preset);
        $this->assertSame([600, 400], $copy->displaySize());
        Storage::disk('public')->assertExists($source->original_path);
    }

    public function test_replace_keeps_the_record_and_deletes_old_files()
    {
        $media = $this->service->upload(UploadedFile::fake()->image('old.jpg', 1200, 800), 'card');
        $oldFiles = [$media->original_path, ...$media->variants->pluck('path')];

        $replaced = $this->service->replace($media, UploadedFile::fake()->image('new.jpg', 900, 900));

        $this->assertSame($media->id, $replaced->id);
        $this->assertSame('new.jpg', $replaced->filename);
        $this->assertSame([600, 400], $replaced->displaySize());
        foreach ($oldFiles as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_delete_removes_record_and_files()
    {
        $media = $this->service->upload(UploadedFile::fake()->image('a.jpg', 800, 600), 'card');
        $files = [$media->original_path, ...$media->variants->pluck('path')];

        $this->service->delete($media);

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseCount('media_variants', 0);
        foreach ($files as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_oversized_images_are_rejected()
    {
        config(['media.max_megapixels' => 1]);

        $this->expectException(MediaException::class);

        $this->service->upload(UploadedFile::fake()->image('huge.jpg', 1500, 1000), 'hero');
    }

    public function test_urls_are_site_relative()
    {
        $media = $this->service->upload(UploadedFile::fake()->image('a.jpg', 800, 600), 'card');

        $this->assertStringStartsWith('/storage/media/', $media->url());
        $this->assertStringContainsString(' 480w', $media->srcset());
        $this->assertSame(Media::count(), 1);
    }
}
