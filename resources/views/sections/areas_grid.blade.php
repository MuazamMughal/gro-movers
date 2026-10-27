{{-- Links to service area pages. --}}
@php($dark = ($s['background'] ?? '') === 'dark')
@if ($items->isNotEmpty())
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" :dark="$dark" />
    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($items as $area)
            <li>
                <a href="{{ $area->publicPath() }}" @class([
                    'flex items-center gap-3 rounded-xl border px-5 py-4 font-semibold transition-colors',
                    'border-white/15 hover:bg-white/10' => $dark,
                    'border-border bg-white text-secondary hover:border-tertiary hover:text-tertiary-strong' => ! $dark,
                ])>
                    <x-site.icon name="map-pin" class="size-5 shrink-0 text-tertiary" />
                    <span>Movers in {{ $area->name }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    @if (! empty($s['button']))
        <div class="mt-10 text-center"><x-site.button :link="$s['button']" :variant="$dark ? 'light' : 'outline'" /></div>
    @endif
</x-site.section>
@endif
