<?php

namespace App\Services\Media;

use App\Models\Media;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Stores uploads and builds the cropped, resized WebP + JPG/PNG variants.
 * The original file is always kept so an image can be re-cropped later.
 *
 * Crop boxes are {x, y, w, h, rotate} in pixels of the original image after
 * EXIF orientation; `rotate` (multiple of 90, clockwise) is applied before cropping.
 */
class MediaService
{
    public function __construct(private ?ImageManager $images = null)
    {
        // GD to match cPanel hosting (Imagick is often missing there).
        $this->images ??= new ImageManager(new Driver);
    }

    /**
     * @param  array{x?: int|float, y?: int|float, w?: int|float, h?: int|float, rotate?: int}|null  $crop
     */
    public function upload(UploadedFile $file, ?string $preset = null, ?array $crop = null, array $attributes = []): Media
    {
        $this->assertPreset($preset);

        $folder = now()->format('Y/m');
        $uuid = (string) Str::uuid();
        $isSvg = $this->isSvg($file);
        $extension = $isSvg ? 'svg' : $this->extensionFor($file->getMimeType());
        $originalPath = $this->directory()."/{$folder}/{$uuid}-original.{$extension}";

        if ($isSvg) {
            $this->disk()->put($originalPath, $this->sanitizeSvg($file->getContent()));
            [$width, $height] = $this->svgSize($this->disk()->get($originalPath));
        } else {
            $this->assertProcessable($file->getRealPath());
            $this->disk()->putFileAs(dirname($originalPath), $file, basename($originalPath));
            $oriented = $this->images->decodePath($file->getRealPath());
            [$width, $height] = [$oriented->width(), $oriented->height()];
        }

        $media = Media::query()->create([
            'uuid' => $uuid,
            'disk' => config('media.disk'),
            'folder' => $folder,
            'filename' => $this->cleanFilename($file->getClientOriginalName()),
            'original_path' => $originalPath,
            'mime' => $isSvg ? 'image/svg+xml' : $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt' => $attributes['alt'] ?? $this->altFromFilename($file->getClientOriginalName()),
            'title' => $attributes['title'] ?? null,
            'caption' => $attributes['caption'] ?? null,
            'preset' => $preset,
            'crop' => null,
        ]);

        if (! $isSvg) {
            $this->process($media, $preset, $crop);
        }

        return $media->load('variants');
    }

    /**
     * Rebuild the variants from the original with a new preset and/or crop box.
     */
    public function recrop(Media $media, ?string $preset, ?array $crop): Media
    {
        $this->assertPreset($preset);

        if ($this->isSvgMedia($media)) {
            return $media;
        }

        $this->process($media, $preset, $crop);

        return $media->load('variants');
    }

    /**
     * New media record cut from an existing original (e.g. the same photo as a
     * hero and as a card) so other uses of the source stay untouched.
     */
    public function derive(Media $source, ?string $preset, ?array $crop): Media
    {
        $this->assertPreset($preset);

        $uuid = (string) Str::uuid();
        $extension = pathinfo($source->original_path, PATHINFO_EXTENSION);
        $originalPath = $this->directory()."/{$source->folder}/{$uuid}-original.{$extension}";

        $this->disk()->copy($source->original_path, $originalPath);

        $media = $source->replicate(['uuid', 'original_path', 'preset', 'crop']);
        $media->fill(['uuid' => $uuid, 'original_path' => $originalPath, 'preset' => $preset])->save();

        if (! $this->isSvgMedia($media)) {
            $this->process($media, $preset, $crop);
        }

        return $media->load('variants');
    }

    /**
     * Swap the file but keep the record (and every place it is used).
     * The crop resets to the centered default for the current preset.
     */
    public function replace(Media $media, UploadedFile $file): Media
    {
        $isSvg = $this->isSvg($file);

        if ($isSvg && ! ($this->presetConfig($media->preset)['svg'] ?? false)) {
            throw new MediaException('SVG files can only be used as logos.');
        }

        if (! $isSvg) {
            $this->assertProcessable($file->getRealPath());
        }

        $oldOriginal = $media->original_path;
        $extension = $isSvg ? 'svg' : $this->extensionFor($file->getMimeType());
        $originalPath = $this->directory()."/{$media->folder}/".Str::uuid()."-original.{$extension}";

        if ($isSvg) {
            $this->disk()->put($originalPath, $this->sanitizeSvg($file->getContent()));
            [$width, $height] = $this->svgSize($this->disk()->get($originalPath));
        } else {
            $this->disk()->putFileAs(dirname($originalPath), $file, basename($originalPath));
            $image = $this->images->decodePath($file->getRealPath());
            [$width, $height] = [$image->width(), $image->height()];
        }

        $media->fill([
            'filename' => $this->cleanFilename($file->getClientOriginalName()),
            'original_path' => $originalPath,
            'mime' => $isSvg ? 'image/svg+xml' : $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'crop' => null,
        ])->save();

        $this->disk()->delete($oldOriginal);

        if ($isSvg) {
            $this->deleteVariants($media);
        } else {
            $this->process($media, $media->preset, null);
        }

        return $media->load('variants');
    }

    /** Remove the record and every file belonging to it. */
    public function delete(Media $media): void
    {
        $this->deleteVariants($media);
        $this->disk()->delete($media->original_path);
        $media->forceDelete();
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    public function presetConfig(?string $preset): array
    {
        return $preset === null ? [] : config("media.presets.{$preset}", []);
    }

    private function process(Media $media, ?string $preset, ?array $crop): void
    {
        $config = $this->presetConfig($preset);
        $image = $this->images->decodePath($this->disk()->path($media->original_path));

        $rotate = (int) ($crop['rotate'] ?? 0);
        if ($rotate % 360 !== 0) {
            // Intervention v4 rotates clockwise for positive angles, like the cropper.
            $image->rotate($rotate);
        }

        $box = $this->resolveCropBox($image, $config, $crop);
        $image->crop($box['w'], $box['h'], $box['x'], $box['y']);

        [$outWidth, $outHeight] = $this->outputSize($box['w'], $box['h'], $config);
        $image->resize($outWidth, $outHeight);

        $fallback = $this->fallbackFormat($media);
        $widths = $this->responsiveWidths($outWidth);
        $uuid = (string) Str::uuid(); // new file names on every (re)process bust browser caches
        $created = [];

        // Largest first, scaling the same image down step by step.
        foreach (array_reverse($widths) as $width) {
            if ($width < $image->width()) {
                $image->scaleDown(width: $width);
            }

            foreach (['webp', $fallback] as $format) {
                $path = $this->directory()."/{$media->folder}/{$uuid}-{$width}.{$format}";
                $encoded = $image->encode($this->encoder($format));
                $this->disk()->put($path, $encoded->toString());

                $created[] = [
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'format' => $format,
                    'path' => $path,
                    'size' => $encoded->size(),
                ];
            }
        }

        DB::transaction(function () use ($media, $preset, $box, $rotate, $created) {
            $this->deleteVariants($media);
            $media->variants()->createMany($created);
            $media->forceFill([
                'preset' => $preset,
                'crop' => [...$box, 'rotate' => $rotate],
            ])->save();
        });
    }

    /**
     * Use the requested box (clamped to the image) or, without one, the largest
     * centered box with the preset's aspect ratio.
     *
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function resolveCropBox(ImageInterface $image, array $config, ?array $crop): array
    {
        $imgW = $image->width();
        $imgH = $image->height();

        if (isset($crop['w'], $crop['h']) && $crop['w'] > 0 && $crop['h'] > 0) {
            $x = (int) max(0, min(round($crop['x'] ?? 0), $imgW - 1));
            $y = (int) max(0, min(round($crop['y'] ?? 0), $imgH - 1));

            return [
                'x' => $x,
                'y' => $y,
                'w' => (int) max(1, min(round($crop['w']), $imgW - $x)),
                'h' => (int) max(1, min(round($crop['h']), $imgH - $y)),
            ];
        }

        if (empty($config['width']) || empty($config['height'])) {
            return ['x' => 0, 'y' => 0, 'w' => $imgW, 'h' => $imgH];
        }

        $ratio = $config['width'] / $config['height'];
        $w = $imgW;
        $h = (int) round($w / $ratio);

        if ($h > $imgH) {
            $h = $imgH;
            $w = (int) round($h * $ratio);
        }

        return [
            'x' => (int) floor(($imgW - $w) / 2),
            'y' => (int) floor(($imgH - $h) / 2),
            'w' => $w,
            'h' => $h,
        ];
    }

    /**
     * Fixed presets resize to the exact preset aspect, never upscaling beyond the crop.
     *
     * @return array{0: int, 1: int}
     */
    private function outputSize(int $cropW, int $cropH, array $config): array
    {
        if (! empty($config['width']) && ! empty($config['height'])) {
            $width = min($config['width'], $cropW);

            return [$width, (int) round($width * $config['height'] / $config['width'])];
        }

        $max = $config['max_width'] ?? config('media.free_max_width');
        $width = min($max, $cropW);

        return [$width, max(1, (int) round($cropH * $width / $cropW))];
    }

    /**
     * @return list<int> ascending, always ending with the output width
     */
    private function responsiveWidths(int $outWidth): array
    {
        $widths = array_values(array_filter(config('media.widths'), fn (int $w) => $w < $outWidth));
        $widths[] = $outWidth;

        return $widths;
    }

    private function fallbackFormat(Media $media): string
    {
        // Only formats that can carry transparency need a PNG fallback; JPG sources stay JPG.
        return in_array($media->mime, ['image/png', 'image/webp'], true) ? 'png' : 'jpg';
    }

    private function encoder(string $format): WebpEncoder|JpegEncoder|PngEncoder
    {
        return match ($format) {
            'webp' => new WebpEncoder(quality: config('media.quality.webp')),
            'jpg' => new JpegEncoder(quality: config('media.quality.jpg'), progressive: true),
            'png' => new PngEncoder,
        };
    }

    private function deleteVariants(Media $media): void
    {
        $paths = $media->variants()->pluck('path')->all();

        if ($paths !== []) {
            $this->disk()->delete($paths);
        }

        $media->variants()->delete();
        $media->unsetRelation('variants');
    }

    private function assertPreset(?string $preset): void
    {
        if ($preset !== null && ! array_key_exists($preset, config('media.presets'))) {
            throw new MediaException("Unknown image preset [{$preset}].");
        }
    }

    private function assertProcessable(string $path): void
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new MediaException('The file is not a readable image.');
        }

        if (config('media.max_megapixels') * 1_000_000 < $info[0] * $info[1]) {
            throw new MediaException('The image is too large ('.round($info[0] * $info[1] / 1_000_000).' megapixels). Please upload one under '.config('media.max_megapixels').' megapixels.');
        }
    }

    private function isSvg(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), ['image/svg+xml', 'image/svg'], true)
            || strtolower($file->getClientOriginalExtension()) === 'svg';
    }

    private function isSvgMedia(Media $media): bool
    {
        return $media->mime === 'image/svg+xml';
    }

    private function sanitizeSvg(string $content): string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($content);

        if ($clean === false || trim($clean) === '') {
            throw new MediaException('The SVG file could not be read safely.');
        }

        return $clean;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function svgSize(string $svg): array
    {
        if (preg_match('/viewBox\s*=\s*["\']\s*[\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $m)) {
            return [(int) round((float) $m[1]), (int) round((float) $m[2])];
        }

        $w = preg_match('/\swidth\s*=\s*["\']([\d.]+)/i', $svg, $mw) ? (int) $mw[1] : null;
        $h = preg_match('/\sheight\s*=\s*["\']([\d.]+)/i', $svg, $mh) ? (int) $mh[1] : null;

        return [$w, $h];
    }

    private function extensionFor(?string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function cleanFilename(string $name): string
    {
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'image';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return Str::limit($base, 120, '').($ext !== '' ? ".{$ext}" : '');
    }

    /** "moving-truck_dallas-2024.jpg" → "Moving truck dallas 2024" (admin should refine). */
    private function altFromFilename(string $name): string
    {
        $words = Str::of(pathinfo($name, PATHINFO_FILENAME))->replaceMatches('/[_\-.]+/', ' ')->squish();

        return Str::limit(Str::ucfirst((string) $words), 150, '');
    }

    private function directory(): string
    {
        return trim(config('media.directory'), '/');
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('media.disk'));
    }
}
