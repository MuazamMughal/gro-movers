@extends('layouts.site')

{{-- One service area (city). Automatic hero/FAQ/CTA fill gaps (App\Sections\AutoSections). --}}
@section('content')
    {!! $rendered->html !!}

    @if ($services->isNotEmpty())
        <x-site.section background="muted">
            <x-site.heading :heading="'Moving services in '.$area->city" :intro="'Local crews who know '.$area->city.'. Pick a service for details and pricing.'" />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $item)
                    <a href="{{ $item['url'] }}" class="group rounded-2xl border border-border bg-white p-6 shadow-sm transition-shadow hover:shadow-lg">
                        <h3 class="text-lg font-bold group-hover:text-tertiary">{{ $item['title'] }} in {{ $area->city }}</h3>
                        @if ($item['short_description'])
                            <p class="mt-2 text-secondary-light">{{ $item['short_description'] }}</p>
                        @endif
                        <span class="mt-4 inline-flex items-center gap-1 font-semibold text-tertiary">See details<x-site.icon name="arrow-right" class="size-4" /></span>
                    </a>
                @endforeach
            </div>
        </x-site.section>
    @endif

    @if ($area->map_embed || ($area->lat && $area->lng))
        @php($src = $area->map_embed ?: 'https://www.google.com/maps?q='.$area->lat.','.$area->lng.'&z=12&output=embed')
        <x-site.section background="light" class="py-12!">
            <iframe src="{{ $src }}" title="Map of {{ $area->name }}" class="h-80 w-full rounded-2xl border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            @if ($area->zip_codes)
                <p class="mt-4 text-sm text-secondary-light">ZIP codes we serve in {{ $area->city }}: {{ implode(', ', $area->zip_codes) }}</p>
            @endif
        </x-site.section>
    @endif

    @if ($neighbors->isNotEmpty())
        <x-site.section background="light" class="py-12!">
            <h2 class="text-center text-2xl font-extrabold">Also moving families near {{ $area->city }}</h2>
            <ul class="mt-6 flex flex-wrap justify-center gap-3">
                @foreach ($neighbors as $neighbor)
                    <li><a href="{{ $neighbor->publicPath() }}" class="inline-flex items-center gap-2 rounded-full border border-border px-5 py-2.5 font-semibold hover:border-tertiary hover:text-tertiary"><x-site.icon name="map-pin" class="size-4 text-tertiary" />Movers in {{ $neighbor->name }}</a></li>
                @endforeach
            </ul>
        </x-site.section>
    @endif

    {!! $closing->html !!}
@endsection
