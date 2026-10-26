<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Database rows only: no file is written to disk.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $folder = now()->format('Y/m');

        return [
            'uuid' => $uuid,
            'disk' => 'public',
            'folder' => $folder,
            'filename' => fake()->slug(3).'.jpg',
            'original_path' => "media/{$folder}/{$uuid}-original.jpg",
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(40_000, 900_000),
            'width' => 1920,
            'height' => 800,
            'alt' => fake()->sentence(5),
            'title' => null,
            'caption' => null,
            'preset' => 'hero',
            'crop' => ['x' => 0, 'y' => 0, 'w' => 1920, 'h' => 800, 'rotate' => 0],
        ];
    }
}
