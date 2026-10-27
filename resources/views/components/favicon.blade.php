{{--
    Browser tab icon for the site and the admin: the favicon uploaded in
    Settings > Branding, otherwise the logo mark in public/ (favicon.ico/png).
--}}
@if ($favicon = \App\Models\Media::query()->with('variants')->find(\App\Support\Settings::get('branding.favicon_id')))
    <link rel="icon" href="{{ $favicon->url(480) }}" type="image/{{ $favicon->isSvg() ? 'svg+xml' : 'webp' }}">
    <link rel="apple-touch-icon" href="{{ $favicon->url(480, $favicon->fallbackFormat() ?? 'webp') }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.png" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif
