<?php

namespace Database\Seeders\Content;

use App\Models\Media;
use App\Services\Media\MediaService;
use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Neutral placeholder images drawn with GD at seed time: a brand color, a faint
 * grid and a simple line icon. They go through the real upload pipeline (crop
 * presets, WebP + JPG variants), so there are no stock photos to license and the
 * cropper and responsive images are exercised from day one.
 */
class PlaceholderMedia
{
    public const NAVY = '#0A2E5C';

    public const ORANGE = '#C55014';

    public const MUTED = '#F1F5FA';

    public const SKY = '#DCE8F5';

    /** Source sizes per preset (uploads are center-cropped to the preset). */
    private const SOURCE = [
        'hero' => [2000, 900],
        'card' => [900, 600],
        'blog' => [1400, 735],
        'square' => [800, 800],
    ];

    public function __construct(private readonly MediaService $media) {}

    /**
     * @param  string  $icon  truck, box, house, building, warehouse, pin, route, calendar, clipboard, person, dollar, piano, key
     */
    public function make(string $name, string $preset, string $icon, string $alt, string $bg = self::NAVY, string $fg = '#FFFFFF'): Media
    {
        [$w, $h] = self::SOURCE[$preset];
        $path = $this->draw($w, $h, $bg, $fg, $icon);

        try {
            $file = new UploadedFile($path, "{$name}.jpg", 'image/jpeg', null, true);

            return $this->media->upload($file, $preset, null, ['alt' => $alt, 'title' => $alt]);
        } finally {
            @unlink($path);
        }
    }

    private function draw(int $w, int $h, string $bg, string $fg, string $icon): string
    {
        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);

        $bgColor = $this->color($img, $bg);
        $fgColor = $this->color($img, $fg);
        $grid = $this->color($img, $fg, 118); // nearly transparent lines

        imagefilledrectangle($img, 0, 0, $w, $h, $bgColor);

        // Faint grid, like the dark sections of the site.
        imagesetthickness($img, 1);
        $step = (int) max(40, round(min($w, $h) / 12));
        for ($x = 0; $x < $w; $x += $step) {
            imageline($img, $x, 0, $x, $h, $grid);
        }
        for ($y = 0; $y < $h; $y += $step) {
            imageline($img, 0, $y, $w, $y, $grid);
        }

        // Icon in a soft circle, centered.
        $size = (int) round(min($w, $h) * 0.34);
        $cx = (int) round($w / 2);
        $cy = (int) round($h / 2);
        imagefilledellipse($img, $cx, $cy, (int) ($size * 1.9), (int) ($size * 1.9), $this->color($img, $fg, 112));

        imagesetthickness($img, max(4, (int) round($size / 14)));
        $this->icon($img, $icon, $cx, $cy, $size, $fgColor, $bgColor);

        // JPEG source: no transparency needed, so the fallback variants are small JPGs.
        $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
        imagejpeg($img, $path, 92);
        imagedestroy($img);

        return $path;
    }

    private function icon(GdImage $img, string $icon, int $cx, int $cy, int $s, int $fg, int $bg): void
    {
        $u = $s / 10; // icon grid unit
        $p = fn (float $x, float $y) => [(int) round($cx + $x * $u), (int) round($cy + $y * $u)];
        $rect = function (float $x1, float $y1, float $x2, float $y2, bool $fill = false) use ($img, $p, $fg) {
            [$a, $b] = $p($x1, $y1);
            [$c, $d] = $p($x2, $y2);
            $fill ? imagefilledrectangle($img, $a, $b, $c, $d, $fg) : imagerectangle($img, $a, $b, $c, $d, $fg);
        };
        $line = function (float $x1, float $y1, float $x2, float $y2) use ($img, $p, $fg) {
            [$a, $b] = $p($x1, $y1);
            [$c, $d] = $p($x2, $y2);
            imageline($img, $a, $b, $c, $d, $fg);
        };
        $circle = function (float $x, float $y, float $r, bool $fill = false, ?int $color = null) use ($img, $p, $u, $fg) {
            [$a, $b] = $p($x, $y);
            $d = (int) round($r * 2 * $u);
            $fill ? imagefilledellipse($img, $a, $b, $d, $d, $color ?? $fg) : imageellipse($img, $a, $b, $d, $d, $color ?? $fg);
        };
        $poly = function (array $points, bool $fill = false) use ($img, $p, $fg) {
            $flat = [];
            foreach ($points as [$x, $y]) {
                array_push($flat, ...$p($x, $y));
            }
            $fill ? imagefilledpolygon($img, $flat, $fg) : imagepolygon($img, $flat, $fg);
        };

        match ($icon) {
            'truck' => (function () use ($rect, $poly, $circle, $bg) {
                $rect(-6, -3.5, 2, 3);
                $poly([[2, -1.5], [4.6, -1.5], [6.4, 0.8], [6.4, 3], [2, 3]]);
                $circle(-3.2, 3.6, 1.5, true);
                $circle(3.8, 3.6, 1.5, true);
                $circle(-3.2, 3.6, 0.6, true, $bg);
                $circle(3.8, 3.6, 0.6, true, $bg);
            })(),
            'box' => (function () use ($poly, $line) {
                $poly([[-5, -2], [0, -4.5], [5, -2], [5, 3.5], [0, 6], [-5, 3.5]]);
                $line(-5, -2, 0, 0.5);
                $line(5, -2, 0, 0.5);
                $line(0, 0.5, 0, 6);
                $line(-2.5, -3.25, 2.5, -0.75);
            })(),
            'house' => (function () use ($poly, $rect) {
                $poly([[-6, -0.5], [0, -6], [6, -0.5]]);
                $rect(-4.5, -0.5, 4.5, 5.5);
                $rect(-1.3, 2, 1.3, 5.5);
            })(),
            'building' => (function () use ($rect) {
                $rect(-4, -6, 4, 6);
                foreach ([-4.5, -1.5, 1.5] as $y) {
                    $rect(-2.6, $y, -0.9, $y + 1.6, true);
                    $rect(0.9, $y, 2.6, $y + 1.6, true);
                }
                $rect(-1, 3.8, 1, 6);
            })(),
            'warehouse' => (function () use ($poly, $line, $rect) {
                $poly([[-6, -1], [0, -5], [6, -1], [6, 5], [-6, 5]]);
                $rect(-3.5, 0.5, 3.5, 5);
                foreach ([1.8, 3.2] as $y) {
                    $line(-3.5, $y, 3.5, $y);
                }
            })(),
            'pin' => (function () use ($poly, $circle, $bg) {
                $poly([[0, 6], [-4.2, 0.5], [-4.6, -2.2], [-3.4, -4.8], [0, -6.2], [3.4, -4.8], [4.6, -2.2], [4.2, 0.5]], true);
                $circle(0, -2.2, 1.7, true, $bg);
            })(),
            'route' => (function () use ($circle, $line) {
                $circle(-4.5, 3.5, 1.4);
                $circle(4.5, -3.5, 1.4);
                $line(-3.2, 3, 1.5, 3);
                $line(1.5, 3, 1.5, -3);
                $line(1.5, -3, 3.1, -3.3);
            })(),
            'calendar' => (function () use ($rect, $line) {
                $rect(-5, -4, 5, 5);
                $line(-5, -1.5, 5, -1.5);
                $line(-2.5, -5.5, -2.5, -3);
                $line(2.5, -5.5, 2.5, -3);
                foreach ([[-3, 0.5], [0, 0.5], [3, 0.5], [-3, 3], [0, 3]] as [$x, $y]) {
                    $rect($x - 0.6, $y - 0.6, $x + 0.6, $y + 0.6, true);
                }
            })(),
            'clipboard' => (function () use ($rect, $line) {
                $rect(-4.5, -4.5, 4.5, 6);
                $rect(-2, -6, 2, -3.5, true);
                foreach ([-1, 1.5, 4] as $y) {
                    $line(-2.8, $y, 2.8, $y);
                }
            })(),
            'person' => (function () use ($circle, $poly) {
                $circle(0, -2.5, 2.6, true);
                $poly([[-5, 6], [-4.4, 2.8], [-2, 1.2], [2, 1.2], [4.4, 2.8], [5, 6]], true);
            })(),
            'dollar' => (function () use ($circle, $line) {
                $circle(0, 0, 5.5);
                $line(0, -4, 0, 4);
                $line(2, -2.2, -1.5, -2.2);
                $line(-1.5, -2.2, -2, 0);
                $line(-2, 0, 2, 0.2);
                $line(2, 0.2, 1.6, 2.3);
                $line(1.6, 2.3, -2, 2.3);
            })(),
            'piano' => (function () use ($rect) {
                $rect(-6, -3, 6, 4);
                for ($x = -4.5; $x <= 4.5; $x += 1.5) {
                    $rect($x - 0.35, -3, $x + 0.35, 1, true);
                }
            })(),
            'key' => (function () use ($circle, $line) {
                $circle(-3, -1, 2.6);
                $line(-0.6, 0, 5.5, 0);
                $line(4, 0, 4, 2);
                $line(5.5, 0, 5.5, 2.2);
            })(),
            default => $circle(0, 0, 5),
        };
    }

    private function color(GdImage $img, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
    }
}
