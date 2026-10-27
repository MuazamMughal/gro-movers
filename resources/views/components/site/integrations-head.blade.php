@props(['preview' => false])
{{--
    Search Console verification, analytics IDs for the deferred loader (resources/js/site/lib/analytics.ts)
    and the admin's custom <head> code (Settings → Integrations). Analytics never blocks rendering:
    GTM / GA4 / Pixel load on first interaction or when the browser is idle.
--}}
@php
    use App\Support\Settings;

    $verification = (string) Settings::get('integrations.search_console_verification');
    // Accept the full <meta> tag Google shows, or just its content value.
    if (preg_match('/content=["\']([^"\']+)["\']/i', $verification, $m)) {
        $verification = $m[1];
    }
    $analytics = array_filter([
        'gtm' => Settings::get('integrations.gtm_id'),
        // GA4 runs through GTM when both are set, so visits are not counted twice.
        'ga4' => Settings::get('integrations.gtm_id') ? null : Settings::get('integrations.ga4_id'),
        'pixel' => Settings::get('integrations.facebook_pixel_id'),
    ]);
@endphp
@if (trim($verification) !== '')
    <meta name="google-site-verification" content="{{ trim($verification) }}">
@endif
@if ($analytics && ! $preview)
    <script>window.siteAnalytics = @json($analytics);</script>
@endif
@if ($head = Settings::get('integrations.head_scripts'))
    {!! $head !!}
@endif
