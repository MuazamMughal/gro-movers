@props(['old' => [], 'form', 'challengeClass' => ''])
{{--
    Hidden fields shared by the public forms: honeypot, signed start time and
    tracking (page URL, original referrer, UTM tags), which the page script fills.
    With Turnstile keys set, a slot the page script fills with the widget the
    first time the visitor uses the form (lib/turnstile.ts).
--}}
<div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
    <label>Website <input name="{{ \App\Support\FormGuard::HONEYPOT }}" type="text" tabindex="-1" autocomplete="off" value=""></label>
</div>
<input type="hidden" name="{{ \App\Support\FormGuard::FIELD }}" value="{{ \App\Support\FormGuard::token() }}">
<input type="hidden" name="source_url" value="{{ $old['source_url'] ?? '' }}">
<input type="hidden" name="referrer" value="{{ $old['referrer'] ?? '' }}">
<input type="hidden" name="utm" value="{{ $old['utm'] ?? '' }}">
@if ($siteKey = \App\Support\Turnstile::siteKey())
    <div class="{{ $challengeClass }}" data-turnstile data-sitekey="{{ $siteKey }}" data-action="{{ $form }}"></div>
    <noscript><p class="text-sm text-secondary-light">This form uses a spam check that needs JavaScript. Please turn it on{{ ($phone = \App\Support\Settings::get('general.phone')) ? ", or call us at {$phone}" : '' }}.</p></noscript>
@endif
