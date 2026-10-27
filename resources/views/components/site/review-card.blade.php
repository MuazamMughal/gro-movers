@props(['review', 'badge' => false])
{{-- One customer review: stars, text, name, city and source. --}}
<figure {{ $attributes->class('flex flex-col rounded-2xl border border-border bg-white p-6 text-secondary shadow-sm') }}>
    <div class="flex items-center justify-between gap-3">
        <x-site.stars :rating="$review->rating" />
        @if ($badge && $review->source)
            <span class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold">{{ $review->source->label() }}</span>
        @endif
    </div>
    <blockquote class="mt-4 flex-1 text-pretty">“{{ $review->body }}”</blockquote>
    <figcaption class="mt-6 flex items-center gap-3">
        @if ($review->photo)
            <x-picture :media="$review->photo" sizes="48px" class="block size-12 shrink-0 overflow-hidden rounded-full" img-class="size-full object-cover" alt="" />
        @else
            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-muted font-bold" aria-hidden="true">{{ mb_substr($review->name, 0, 1) }}</span>
        @endif
        <span>
            <span class="block font-bold">{{ $review->name }}</span>
            <span class="text-sm text-secondary-light">
                {{ collect([
                    $review->city,
                    $badge ? null : $review->source?->label(),
                    $review->reviewed_at?->format('M Y'),
                ])->filter()->implode(' · ') }}
            </span>
        </span>
    </figcaption>
</figure>
