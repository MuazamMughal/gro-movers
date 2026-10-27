{{-- GTM <noscript> fallback and the admin's custom <body> code (Settings → Integrations). --}}
@if ($gtm = \App\Support\Settings::get('integrations.gtm_id'))
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
@endif
@if ($body = \App\Support\Settings::get('integrations.body_scripts'))
    {!! $body !!}
@endif
