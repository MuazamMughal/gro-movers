{{-- Reviews: horizontal scroll-snap slider on mobile, grid on desktop. No JS needed. --}}
@php($dark = ($s['background'] ?? '') === 'dark')
@if ($items->isNotEmpty())
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :tag="$headingTag" :dark="$dark" />
    @if ($s['show_summary'] ?? true)
        @php($average = round($items->avg('rating'), 1))
        <p class="-mt-6 mb-10 flex items-center justify-center gap-2 font-semibold">
            <x-site.stars :rating="$average" /> {{ number_format($average, 1) }} average from {{ $items->count() }} {{ Str::plural('review', $items->count()) }}
        </p>
    @endif
    <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-3" tabindex="0" aria-label="Customer reviews">
        @foreach ($items as $review)
            <x-site.review-card :review="$review" class="w-[85%] shrink-0 snap-center md:w-auto" />
        @endforeach
    </div>
    @if (! empty($s['button']))
        <div class="mt-10 text-center"><x-site.button :link="$s['button']" :variant="$dark ? 'light' : 'outline'" /></div>
    @endif
</x-site.section>
@endif
