{{--
    Public site layout. Header, footer, top contact bar and the mobile sticky
    Call / Quote bar are added in T11; this is the document shell.
    Expects: $seo (App\Support\SeoData). Optional: $preview (bool) for admin previews,
    $previewNotice (string) to replace the preview banner text.
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
    @fonts('inter')
    @vite('resources/css/site.css')
    <x-site.theme-vars />
    @stack('head')
</head>
<body class="font-sans">
    @if ($preview ?? false)
        <div class="sticky top-0 z-50 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-black" role="status">
            {{ $previewNotice ?? 'Preview of unsaved changes — this is not the live page.' }}
        </div>
    @endif

    <main id="main">
        @yield('content')
    </main>

    @stack('body-end')
</body>
</html>
