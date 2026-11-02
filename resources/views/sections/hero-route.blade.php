{{-- Home hero: headline, a map of the cities we serve and the first step of a quote. Included by sections/hero. --}}
<section id="{{ $anchor }}" class="relative isolate z-10 bg-glow-navy text-primary" data-theme="dark">
    <div class="mx-auto grid max-w-7xl items-center px-4 pt-12 pb-10 sm:px-6 md:pt-16 lg:grid-cols-[1.08fr_0.92fr] lg:gap-x-6 lg:px-8 lg:pb-14">
        <div class="max-w-2xl">
            <{{ $headingTag }} class="text-4xl font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $s['heading'] }}</{{ $headingTag }}>
            @if ($s['subheading'] ?? null)
                <p class="mt-5 text-lg text-pretty text-primary/85 sm:text-xl">{{ $s['subheading'] }}</p>
            @endif
        </div>

        <div class="route-map-frame order-last mx-auto mt-8 w-full max-w-lg lg:order-none lg:-my-6 lg:mt-0 lg:max-w-none"><x-site.route-map /></div>

        <x-site.quick-quote class="mt-8 lg:col-span-2 lg:mt-2" />

        <div class="mt-6 flex flex-wrap items-center justify-between gap-x-8 gap-y-3 lg:col-span-2">
            @if (! empty($s['bullets']))
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-primary/90">
                    @foreach ($s['bullets'] as $bullet)
                        <li class="flex items-center gap-2"><x-site.icon name="circle-check" class="size-4 shrink-0 text-tertiary" />{{ $bullet['text'] }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($phone = \App\Support\Contact::phone())
                <a href="{{ \App\Support\Contact::telHref() }}" class="flex items-center gap-2 text-sm font-semibold whitespace-nowrap hover:text-tertiary-soft" data-call-location="hero"><x-site.icon name="phone" class="size-4 text-tertiary" />Prefer to talk? {{ $phone }}</a>
            @endif
        </div>
    </div>
</section>
