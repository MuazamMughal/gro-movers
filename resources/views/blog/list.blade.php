@extends('layouts.site')

{{--
    /blog and /blog/category/{slug}, with path pagination.
    $intro: rendered hero (and the blog page's own sections) on page 1, null on later pages.
--}}
@section('content')
    @if ($intro)
        {!! $intro->html !!}
    @else
        <div class="bg-muted">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 md:py-16 lg:px-8">
                <h1 class="text-4xl font-extrabold tracking-tight text-balance md:text-5xl">{{ $heading }}</h1>
                <p class="mt-3 text-lg text-secondary-light">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</p>
            </div>
        </div>
    @endif

    <x-site.section>
        @if ($categories->isNotEmpty())
            <nav class="mb-12 flex flex-wrap justify-center gap-2" aria-label="Blog categories">
                @foreach ([['name' => 'All posts', 'url' => '/blog', 'active' => $activeCategory === null], ...$categories->map(fn ($c) => ['name' => $c->name, 'url' => $c->publicPath(), 'active' => $activeCategory === $c->id])] as $item)
                    <a href="{{ $item['url'] }}" @class([
                        'inline-flex min-h-11 items-center rounded-full border px-5 font-semibold transition-colors',
                        'border-secondary bg-secondary text-primary' => $item['active'],
                        'border-border hover:border-tertiary hover:text-tertiary-strong' => ! $item['active'],
                    ]) @if ($item['active']) aria-current="page" @endif>{{ $item['name'] }}</a>
                @endforeach
            </nav>
        @endif

        @if ($featured)
            <div class="mb-10">
                <x-site.post-card :post="$featured" featured heading-tag="h2" />
            </div>
        @endif

        @if ($posts->isNotEmpty())
            <h2 class="sr-only">{{ $featured ? 'More articles' : 'Articles' }}</h2>
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-site.post-card :post="$post" />
                @endforeach
            </div>
        @elseif (! $featured)
            <p class="py-12 text-center text-lg text-secondary-light">New articles are on the way. Check back soon.</p>
        @endif

        <x-site.pagination :paginator="$posts" :base-path="$basePath" />
    </x-site.section>

    {!! $closing->html !!}
@endsection
