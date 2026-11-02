{{-- Hero: page headline. $headingTag is h1 for the page's first hero. --}}
@php
    $dark = ($s['theme'] ?? 'dark') === 'dark';
    $image = $s['background_image'] ?? null;
    $withForm = (bool) ($s['show_quote_form'] ?? false);
@endphp
@if ($withForm)
    @include('sections.hero-route')
@else
<section id="{{ $anchor }}" @class(['relative isolate overflow-hidden', 'bg-glow-navy text-primary' => $dark, 'bg-muted text-secondary' => ! $dark]) data-theme="{{ $dark ? 'dark' : 'muted' }}">
    @if ($image)
        <x-picture :media="$image" sizes="100vw" eager class="absolute inset-0 -z-20" img-class="size-full object-cover" alt="" />
        <div @class(['absolute inset-0 -z-10', 'bg-glow-navy opacity-85' => $dark, 'bg-white/85' => ! $dark])></div>
    @endif
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 md:py-24 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            @if ($s['eyebrow'] ?? null)
                <p @class([
                    'mb-4 inline-flex rounded-full px-3 py-1 text-sm font-semibold',
                    'bg-white/10 text-tertiary-soft ring-1 ring-white/15' => $dark,
                    'bg-white text-tertiary-strong ring-1 ring-tertiary/30' => ! $dark,
                ])>{{ $s['eyebrow'] }}</p>
            @endif
            <{{ $headingTag }} class="text-4xl font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $s['heading'] }}</{{ $headingTag }}>
            @if ($s['subheading'] ?? null)
                <p @class(['mt-6 text-lg text-pretty sm:text-xl', 'text-primary/85' => $dark, 'text-secondary-light' => ! $dark])>{{ $s['subheading'] }}</p>
            @endif
            @if (! empty($s['bullets']))
                <ul class="mx-auto mt-6 grid max-w-xl gap-2 text-left sm:grid-cols-2">
                    @foreach ($s['bullets'] as $bullet)
                        <li class="flex items-start gap-2 font-medium"><x-site.icon name="circle-check" class="mt-0.5 size-5 shrink-0 text-tertiary" />{{ $bullet['text'] }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <x-site.button :link="$s['primary_button'] ?? null" size="lg" />
                <x-site.button :link="$s['secondary_button'] ?? null" :variant="$dark ? 'outline-light' : 'outline'" size="lg" />
                @if (empty($s['primary_button']) && empty($s['secondary_button']))
                    <x-site.button :href="\App\Support\QuoteLink::href()" label="Get a Free Quote" size="lg" />
                    <x-site.call-button :variant="$dark ? 'outline-light' : 'outline'" size="lg" location="hero" />
                @endif
            </div>
        </div>
    </div>
</section>
@endif
