@extends('layouts.site')

{{-- A builder page (home, about, contact, custom pages). $rendered is App\Sections\RenderedSections. --}}
@section('content')
    @unless ($rendered->hasH1)
        <div class="bg-muted">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 md:py-16 lg:px-8">
                <h1 class="text-4xl font-extrabold tracking-tight text-balance md:text-5xl">{{ $page->title }}</h1>
            </div>
        </div>
    @endunless

    {!! $rendered->html !!}
@endsection
