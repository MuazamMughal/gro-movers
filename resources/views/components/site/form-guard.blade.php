@props(['old' => []])
{{--
    Hidden fields shared by the public forms: honeypot, signed start time and
    tracking (page URL, original referrer, UTM tags), which the page script fills.
--}}
<div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
    <label>Website <input name="{{ \App\Support\FormGuard::HONEYPOT }}" type="text" tabindex="-1" autocomplete="off" value=""></label>
</div>
<input type="hidden" name="{{ \App\Support\FormGuard::FIELD }}" value="{{ \App\Support\FormGuard::token() }}">
<input type="hidden" name="source_url" value="{{ $old['source_url'] ?? '' }}">
<input type="hidden" name="referrer" value="{{ $old['referrer'] ?? '' }}">
<input type="hidden" name="utm" value="{{ $old['utm'] ?? '' }}">
