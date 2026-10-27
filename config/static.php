<?php

/*
| Static HTML publisher (App\Services\StaticPublisher): every public page is
| pre-rendered to public/static/{path}/index.html and Apache serves it without PHP
| (see public/.htaccess). Turned on and off in Settings → Static Cache.
*/

return [
    // Where the files go. public/.htaccess expects public/static.
    'path' => env('STATIC_PATH', public_path('static')),

    // Pages per queued build job.
    'chunk' => 25,

    // Rebuild automatically when content changes. Off in tests (they turn it on
    // explicitly) so saving a model in a test never renders the whole site.
    'auto' => env('STATIC_AUTO', env('APP_ENV') !== 'testing'),

    // Rendered pages are minified (whitespace between tags, comments).
    'minify' => true,
];
