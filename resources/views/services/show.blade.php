@extends('layouts.site')

{{-- One service. The automatic hero/FAQ/CTA fill gaps (App\Sections\AutoSections). --}}
@section('content')
    {!! $rendered->html !!}

    @if ($areas)
        <x-site.section background="light">
            <x-site.heading :heading="$service->title.' near you'" intro="Choose your city for local details, reviews and pricing." />
            <ul class="flex flex-wrap justify-center gap-3">
                @foreach ($areas as $area)
                    <li>
                        <a href="{{ $area['url'] }}" class="inline-flex items-center gap-2 rounded-full border border-border px-5 py-2.5 font-semibold hover:border-tertiary hover:text-tertiary">
                            <x-site.icon name="map-pin" class="size-4 text-tertiary" />{{ $service->title }} in {{ $area['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-site.section>
    @endif

    {!! $closing->html !!}
@endsection
