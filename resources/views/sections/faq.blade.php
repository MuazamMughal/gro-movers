{{-- FAQ accordion using <details> (works without JS). FAQPage schema is added by the page (T13). --}}
@php
    $dark = ($s['background'] ?? '') === 'dark';
    // FAQ answers can use tokens ({company}, {phone}, and on city pages {city} …).
    $tokens = new \App\Services\TokenReplacer($area ?? null, $service ?? null);
@endphp
@if ($items->isNotEmpty())
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor" width="narrow">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" :dark="$dark" />
    <div @class(['divide-y rounded-2xl border', 'divide-white/10 border-white/10' => $dark, 'divide-border border-border bg-white' => ! $dark])>
        @foreach ($items as $faq)
            <details class="group px-6 py-5" @if ($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-lg font-semibold [&::-webkit-details-marker]:hidden">
                    <h3>{{ $tokens->replace($faq->question) }}</h3>
                    <x-site.icon name="chevron-down" class="size-5 shrink-0 text-tertiary transition-transform group-open:rotate-180" />
                </summary>
                <x-site.prose class="mt-4 text-base!">{!! $tokens->replaceHtml($faq->answer) !!}</x-site.prose>
            </details>
        @endforeach
    </div>
</x-site.section>
@endif
