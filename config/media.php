<?php

/*
| Media library: storage, upload limits and the crop presets used by the cropper.
| Every processed image is stored as WebP plus a JPG/PNG fallback, at each
| responsive width that fits the preset (see MediaService).
*/

return [
    'disk' => 'public',

    'directory' => 'media',

    // Upload limits.
    'max_upload_kb' => 8192,
    'max_megapixels' => 40, // GD needs ~4 bytes/pixel in memory; keeps shared hosting safe
    'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

    // Responsive widths generated (only those smaller than the output width, plus the output width).
    'widths' => [480, 768, 1200, 1920],

    'quality' => [
        'webp' => 80,
        'jpg' => 82,
    ],

    'presets' => [
        'hero' => ['label' => 'Hero banner', 'width' => 1920, 'height' => 800],
        'card' => ['label' => 'Card', 'width' => 600, 'height' => 400],
        'blog' => ['label' => 'Blog / social share', 'width' => 1200, 'height' => 630],
        'square' => ['label' => 'Square', 'width' => 600, 'height' => 600],
        // Free crop, keeps transparency, SVG allowed.
        'logo' => ['label' => 'Logo (free crop)', 'width' => null, 'height' => null, 'max_width' => 1200, 'svg' => true],
    ],

    // Uploads without a preset (straight into the library) keep their aspect ratio.
    'free_max_width' => 1920,
];
