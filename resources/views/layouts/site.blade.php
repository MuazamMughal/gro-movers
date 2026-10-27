{{--
    Public site layout: top contact bar, sticky header, breadcrumbs, footer and the
    mobile sticky Call / Quote bar.
    Expects: $seo (App\Support\SeoData). Optional: $breadcrumbs (list of {label, url}),
    $preview (bool) for admin previews, $previewNotice (string) for the preview banner,
    $bare (bool) to leave out the header, footer and bars.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-site.seo :seo="$seo" />
    @if ($preview ?? false)
        {{-- Previews must never be indexed, whatever the page's own robots setting says. --}}
        <meta name="robots" content="noindex,nofollow">
    @endif
    @if ($favicon = \App\Models\Media::query()->with('variants')->find(\App\Support\Settings::get('branding.favicon_id')))
        <link rel="icon" href="{{ $favicon->url(480) }}" type="image/{{ $favicon->isSvg() ? 'svg+xml' : 'webp' }}">
        <link rel="apple-touch-icon" href="{{ $favicon->url(480, $favicon->fallbackFormat() ?? 'webp') }}">
    @endif
    <x-site.fonts />
    @vite(['resources/css/site.css', 'resources/js/site/site.ts'])
    <x-site.theme-vars />
    <x-site.schema
        :seo="$seo"
        :breadcrumbs="$breadcrumbs ?? []"
        :nodes="$schema ?? []"
        :business="$schemaBusiness ?? null"
        :faqs="collect([$rendered ?? null, $closing ?? null, $reviews ?? null])->filter(fn ($r) => $r instanceof \App\Sections\RenderedSections)->flatMap->faqs"
    />
    <x-site.integrations-head :preview="$preview ?? false" />
    @stack('head')
</head>
<body @class(['font-sans', 'pb-16 md:pb-0' => ! ($bare ?? false)])>
    <x-site.integrations-body />
    @if ($preview ?? false)
        <div class="sticky top-0 z-50 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-black" role="status">
            {{ $previewNotice ?? 'Preview of unsaved changes — this is not the live page.' }}
        </div>
    @endif

    @unless ($bare ?? false)
        <x-site.top-bar />
        <x-site.header />
    @endunless

    <main id="main">
        @if (! empty($breadcrumbs))
            <x-site.breadcrumbs :items="$breadcrumbs" />
        @endif
        @yield('content')
    </main>

    @unless ($bare ?? false)
        <x-site.footer />
        <x-site.mobile-bar />
    @endunless

    @stack('body-end')
</body>
</html>
