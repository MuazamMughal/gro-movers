{{-- Cards linking to service pages. --}}
@php($dark = ($s['background'] ?? '') === 'dark')
@if ($items->isNotEmpty())
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" :dark="$dark" />
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $item)
            <a href="{{ $item->publicPath() }}" class="group flex flex-col overflow-hidden rounded-2xl border border-border bg-white text-secondary shadow-sm transition-shadow hover:shadow-lg">
                @if ($item->image)
                    <x-picture :media="$item->image" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" class="block aspect-[3/2] overflow-hidden" img-class="size-full object-cover transition-transform duration-300 group-hover:scale-105" />
                @endif
                <div class="flex flex-1 flex-col p-6">
                    <h3 class="text-xl font-bold group-hover:text-tertiary-strong">{{ $item->title }}</h3>
                    @if ($item->short_description)
                        <p class="mt-2 flex-1 text-secondary-light">{{ $item->short_description }}</p>
                    @endif
                    <span class="mt-4 inline-flex items-center gap-1 font-semibold text-tertiary-strong">Learn more<span class="sr-only"> about {{ $item->title }}</span><x-site.icon name="arrow-right" class="size-4" /></span>
                </div>
            </a>
        @endforeach
    </div>
    @if (! empty($s['button']))
        <div class="mt-12 text-center"><x-site.button :link="$s['button']" :variant="$dark ? 'light' : 'outline'" /></div>
    @endif
</x-site.section>
@endif
