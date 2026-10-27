{{-- Quote form with a short sales pitch. --}}
@php($dark = ($s['background'] ?? '') === 'dark')
<x-site.section :background="$s['background'] ?? 'muted'" :anchor="$anchor">
    <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-16">
        <div>
            <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" align="left" :dark="$dark" />
            @if (! empty($s['bullets']))
                <ul class="-mt-4 space-y-3">
                    @foreach ($s['bullets'] as $bullet)
                        <li class="flex items-start gap-3 text-lg"><x-site.icon name="circle-check" class="mt-1 size-5 shrink-0 text-tertiary" />{{ $bullet['text'] }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-8"><x-site.call-button :variant="$dark ? 'outline-light' : 'outline'" location="quote_section" /></div>
        </div>
        <x-site.quote-form :move-types="$items" id="quote-{{ $section->id }}" />
    </div>
</x-site.section>
