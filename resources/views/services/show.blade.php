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

    @if ($posts->isNotEmpty())
        <x-site.section>
            <x-site.heading heading="Moving tips and guides" intro="Advice from our crews to help you plan." />
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-site.post-card :post="$post" />
                @endforeach
            </div>
            <div class="mt-10 text-center"><x-site.button href="/blog" label="More moving guides" variant="outline" /></div>
        </x-site.section>
    @endif

    {!! $closing->html !!}
@endsection
