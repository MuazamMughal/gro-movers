{{-- Feature cards ("Why choose us"). --}}
@php
    $dark = ($s['background'] ?? '') === 'dark';
    $cols = match ($s['columns'] ?? '3') { '2' => 'md:grid-cols-2', '4' => 'md:grid-cols-2 lg:grid-cols-4', default => 'md:grid-cols-2 lg:grid-cols-3' };
@endphp
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" :dark="$dark" />
    <div class="grid gap-6 {{ $cols }}">
        @foreach ($s['cards'] ?? [] as $card)
            <div @class(['rounded-2xl border p-6 shadow-sm', 'border-white/10 bg-white/5' => $dark, 'border-border bg-white' => ! $dark])>
                @if ($card['icon'] ?? null)
                    <span class="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-tertiary/10 text-tertiary"><x-site.icon :name="$card['icon']" class="size-6" /></span>
                @endif
                <h3 class="text-xl font-bold">{{ $card['title'] }}</h3>
                @if ($card['text'] ?? null)
                    <p @class(['mt-2', 'text-primary/80' => $dark, 'text-secondary-light' => ! $dark])>{{ $card['text'] }}</p>
                @endif
                @if (! empty($card['link']))
                    <a href="{{ $card['link']['url'] }}" class="mt-4 inline-flex items-center gap-1 font-semibold text-tertiary hover:text-tertiary-hover">{{ $card['link']['label'] }}<x-site.icon name="arrow-right" class="size-4" /></a>
                @endif
            </div>
        @endforeach
    </div>
</x-site.section>
