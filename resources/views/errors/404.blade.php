@extends('layouts.site')

{{-- Not found: "did you mean" suggestions, the services and cities, and the quote page. Never indexed. --}}
@php
    $seo = \App\Support\Seo::make(title: 'Page not found', path: request()->path(), index: false);
    $suggestions = \App\Support\NotFoundSuggestions::for(request()->path());
    $services = \App\Models\Service::query()->active()->ordered()->limit(8)->get();
    $areas = \App\Models\Area::query()->active()->ordered()->limit(8)->get();
@endphp

@section('content')
    <section class="bg-muted">
        <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 md:py-24">
            <p class="text-sm font-bold tracking-widest text-tertiary-strong uppercase">Error 404</p>
            <h1 class="mt-3 text-4xl font-extrabold tracking-tight text-balance md:text-5xl">We couldn’t find that page</h1>
            <p class="mt-5 text-lg text-secondary-light">The link may be old or mistyped. These pages might help, or get your free quote in two minutes.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <x-site.button href="/get-a-quote" label="Get a Free Quote" size="lg" />
                <x-site.button href="/" label="Go to the home page" variant="outline" size="lg" />
            </div>
        </div>
    </section>

    <x-site.section class="py-14!">
        @if ($suggestions)
            <div class="mx-auto mb-14 max-w-2xl">
                <h2 class="text-2xl font-extrabold">Were you looking for…</h2>
                <ul class="mt-4 divide-y divide-border rounded-2xl border border-border">
                    @foreach ($suggestions as $item)
                        <li><a href="{{ $item['url'] }}" class="flex items-center justify-between gap-4 px-5 py-4 font-semibold hover:text-tertiary-strong">{{ $item['label'] }}<x-site.icon name="arrow-right" class="size-4 shrink-0" /></a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-10 md:grid-cols-2">
            @if ($services->isNotEmpty())
                <div>
                    <h2 class="text-xl font-extrabold">Moving services</h2>
                    <ul class="mt-4 space-y-2">
                        @foreach ($services as $service)
                            <li><a href="{{ $service->publicPath() }}" class="inline-flex items-center gap-2 hover:text-tertiary"><x-site.icon name="truck" class="size-5 text-tertiary" />{{ $service->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($areas->isNotEmpty())
                <div>
                    <h2 class="text-xl font-extrabold">Areas we serve</h2>
                    <ul class="mt-4 space-y-2">
                        @foreach ($areas as $area)
                            <li><a href="{{ $area->publicPath() }}" class="inline-flex items-center gap-2 hover:text-tertiary"><x-site.icon name="map-pin" class="size-5 text-tertiary" />Movers in {{ $area->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <p class="mt-12 text-center text-secondary-light">
            Prefer to talk? Call us at <a href="{{ \App\Support\Contact::telHref() }}" class="font-bold text-secondary hover:text-tertiary-strong" data-call-location="404">{{ \App\Support\Contact::phone() }}</a>.
        </p>
    </x-site.section>
@endsection
