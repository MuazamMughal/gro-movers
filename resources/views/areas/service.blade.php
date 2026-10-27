@extends('layouts.site')

{{-- "{Service} in {City}": the shared template (or this page's own content) with tokens filled in. --}}
@section('content')
    {!! $rendered->html !!}

    <x-site.section background="muted" class="py-12!">
        <div class="grid gap-10 md:grid-cols-2">
            <div>
                <h2 class="text-2xl font-extrabold">More about {{ $service->title }}</h2>
                <p class="mt-2 text-secondary-light">{{ $service->short_description }}</p>
                <a href="{{ $service->publicPath() }}" class="mt-4 inline-flex items-center gap-1 font-semibold text-tertiary hover:text-tertiary-hover">{{ $service->title }} overview<x-site.icon name="arrow-right" class="size-4" /></a>
                <a href="{{ $area->publicPath() }}" class="mt-2 flex items-center gap-1 font-semibold text-tertiary hover:text-tertiary-hover">All moving services in {{ $area->city }}<x-site.icon name="arrow-right" class="size-4" /></a>
            </div>
            @if ($siblings->isNotEmpty())
                <div>
                    <h2 class="text-2xl font-extrabold">Other services in {{ $area->city }}</h2>
                    <ul class="mt-4 space-y-2">
                        @foreach ($siblings as $item)
                            <li><a href="{{ $item['url'] }}" class="inline-flex items-center gap-2 font-semibold hover:text-tertiary"><x-site.icon name="circle-check" class="size-5 text-tertiary" />{{ $item['title'] }} in {{ $area->city }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </x-site.section>

    {!! $closing->html !!}
@endsection
