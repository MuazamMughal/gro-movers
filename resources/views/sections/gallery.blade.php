{{-- Photo grid. --}}
@php($cols = match ($s['columns'] ?? '3') { '2' => 'sm:grid-cols-2', '4' => 'sm:grid-cols-2 lg:grid-cols-4', default => 'sm:grid-cols-2 lg:grid-cols-3' })
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :tag="$headingTag" :dark="($s['background'] ?? '') === 'dark'" />
    <div class="grid gap-4 {{ $cols }}">
        @foreach ($s['images'] ?? [] as $photo)
            <figure>
                <x-picture :media="$photo['image'] ?? null" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" class="block overflow-hidden rounded-xl" img-class="aspect-[3/2] w-full object-cover" />
                @if ($photo['caption'] ?? null)
                    <figcaption class="mt-2 text-sm text-secondary-light">{{ $photo['caption'] }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
</x-site.section>
