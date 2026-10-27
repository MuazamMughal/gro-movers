@extends('layouts.site')

{{-- /service-areas: page sections, then every active city grouped by state, then the closing CTA. --}}
@section('content')
    {!! $rendered->html !!}

    @if ($states->isNotEmpty())
        <x-site.section>
            @foreach ($states as $state => $areas)
                <div @class(['mt-14' => ! $loop->first])>
                    @if ($states->count() > 1)
                        <h2 class="mb-6 text-2xl font-extrabold tracking-tight">{{ $state }}</h2>
                    @else
                        <x-site.heading :heading="'Cities we serve in '.$state" />
                    @endif
                    <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($areas as $area)
                            <li class="group relative overflow-hidden rounded-2xl border border-border bg-white shadow-sm transition-shadow hover:shadow-lg">
                                @if ($area->image)
                                    <x-picture :media="$area->image" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" class="block aspect-[3/2] overflow-hidden" img-class="size-full object-cover transition-transform duration-300 group-hover:scale-105" alt="" />
                                @endif
                                <div class="p-6">
                                    <h3 class="text-xl font-bold"><a href="{{ $area->publicPath() }}" class="after:absolute after:inset-0 group-hover:text-tertiary">Movers in {{ $area->name }}</a></h3>
                                    @if ($area->intro)
                                        <p class="mt-2 line-clamp-3 text-secondary-light">{{ $area->intro }}</p>
                                    @endif
                                    <span class="mt-4 inline-flex items-center gap-1 font-semibold text-tertiary">View {{ $area->city }}<x-site.icon name="arrow-right" class="size-4" /></span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </x-site.section>
    @endif

    {!! $closing->html !!}
@endsection
