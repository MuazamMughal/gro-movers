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
            <figure class="flex w-[85%] shrink-0 snap-center flex-col rounded-2xl border border-border bg-white p-6 text-secondary shadow-sm md:w-auto">
                <x-site.stars :rating="$review->rating" />
                <blockquote class="mt-4 flex-1 text-pretty">“{{ $review->body }}”</blockquote>
                <figcaption class="mt-6 flex items-center gap-3">
                    @if ($review->photo)
                        <x-picture :media="$review->photo" sizes="48px" class="block size-12 overflow-hidden rounded-full" img-class="size-full object-cover" />
                    @else
                        <span class="flex size-12 items-center justify-center rounded-full bg-muted font-bold" aria-hidden="true">{{ mb_substr($review->name, 0, 1) }}</span>
                    @endif
                    <span>
                        <span class="block font-bold">{{ $review->name }}</span>
                        <span class="text-sm text-secondary-light">{{ collect([$review->city, ucfirst($review->source?->value ?? '')])->filter()->implode(' · ') }}</span>
                    </span>
                </figcaption>
            </figure>
        @endforeach
    </div>
    @if (! empty($s['button']))
        <div class="mt-10 text-center"><x-site.button :link="$s['button']" :variant="$dark ? 'light' : 'outline'" /></div>
    @endif
</x-site.section>
@endif
