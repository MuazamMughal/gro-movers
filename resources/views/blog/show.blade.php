@extends('layouts.site')

{{-- One blog post. $body is App\Blog\RenderedPost (split before the 3rd H2 for the mid-article CTA). --}}
@section('content')
    @php
        $published = \App\Support\SiteTime::local($post->published_at);
        $updated = \App\Support\SiteTime::local($post->updated_at);
        $showUpdated = $published && $updated && $updated->diffInDays($published, true) >= 1;
    @endphp

    <article>
        <header class="bg-muted">
            <div class="mx-auto max-w-3xl px-4 pt-12 pb-10 sm:px-6 md:pt-16">
                @if ($post->category)
                    <a href="{{ $post->category->publicPath() }}" class="inline-flex rounded-full bg-white px-3 py-1 text-sm font-semibold text-tertiary-strong ring-1 ring-tertiary/30 hover:ring-tertiary">{{ $post->category->name }}</a>
                @endif
                <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-balance md:text-5xl">{{ $post->title }}</h1>
                @if ($post->excerpt)
                    <p class="mt-5 text-xl text-pretty text-secondary-light">{{ $post->excerpt }}</p>
                @endif
                <p class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-1 text-secondary-light">
                    @if ($post->author)
                        <span>By <span class="font-semibold text-secondary">{{ $post->author->name }}</span></span>
                        <span aria-hidden="true">·</span>
                    @endif
                    @if ($published)
                        <time datetime="{{ $published->toIso8601String() }}">{{ $published->format('F j, Y') }}</time>
                        <span aria-hidden="true">·</span>
                    @endif
                    @if ($showUpdated)
                        <span>Updated <time datetime="{{ $updated->toIso8601String() }}">{{ $updated->format('F j, Y') }}</time></span>
                        <span aria-hidden="true">·</span>
                    @endif
                    <span>{{ $post->reading_minutes }} min read</span>
                </p>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 md:py-14">
            @if ($post->featuredImage)
                <x-picture :media="$post->featuredImage" sizes="(min-width: 800px) 768px, 100vw" eager class="-mt-2 mb-10 block" img-class="w-full rounded-2xl" />
            @endif

            @if (count($body->toc) >= 2)
                <nav class="mb-10 rounded-2xl border border-border bg-muted/60 p-6" aria-labelledby="toc-heading">
                    <details open>
                        <summary class="flex cursor-pointer items-center gap-2 text-lg font-bold" id="toc-heading">
                            <x-site.icon name="list" class="size-5 text-tertiary" /> In this article
                        </summary>
                        <ol class="mt-4 list-decimal space-y-2 pl-6 marker:font-semibold marker:text-tertiary-strong">
                            @foreach ($body->toc as $item)
                                <li><a href="#{{ $item['id'] }}" class="hover:text-tertiary-strong hover:underline">{{ $item['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </details>
                </nav>
            @endif

            <x-site.prose>{!! $body->before !!}</x-site.prose>

            @if ($body->hasMidCta())
                <x-site.inline-cta />
                <x-site.prose>{!! $body->after !!}</x-site.prose>
            @endif

            @if ($post->tags->isNotEmpty())
                <ul class="mt-12 flex flex-wrap gap-2" aria-label="Tags">
                    @foreach ($post->tags as $tag)
                        <li class="rounded-full bg-muted px-3 py-1 text-sm font-medium">#{{ $tag->name }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($post->author)
                <aside class="mt-12 flex flex-col gap-5 rounded-2xl border border-border p-6 sm:flex-row sm:items-start" aria-label="About the author">
                    @if ($post->author->photo)
                        <x-picture :media="$post->author->photo" sizes="96px" class="shrink-0" img-class="size-24 rounded-full object-cover" alt="" />
                    @endif
                    <div>
                        <p class="text-sm font-semibold tracking-wider text-tertiary-strong uppercase">Written by</p>
                        <p class="mt-1 text-xl font-bold">{{ $post->author->name }}</p>
                        @if ($post->author->job_title)
                            <p class="text-secondary-light">{{ $post->author->job_title }}</p>
                        @endif
                        @if ($post->author->bio)
                            <p class="mt-3">{{ $post->author->bio }}</p>
                        @endif
                    </div>
                </aside>
            @endif

            @if ($previous || $next)
                <nav class="mt-12 grid gap-4 sm:grid-cols-2" aria-label="More posts">
                    @if ($previous)
                        <a href="{{ $previous->publicPath() }}" class="group rounded-2xl border border-border p-5 hover:border-tertiary">
                            <span class="flex items-center gap-1 text-sm text-secondary-light"><x-site.icon name="arrow-left" class="size-4" /> Previous article</span>
                            <span class="mt-1 block font-bold group-hover:text-tertiary-strong">{{ $previous->title }}</span>
                        </a>
                    @endif
                    @if ($next)
                        <a href="{{ $next->publicPath() }}" class="group rounded-2xl border border-border p-5 text-right hover:border-tertiary sm:col-start-2">
                            <span class="flex items-center justify-end gap-1 text-sm text-secondary-light">Next article <x-site.icon name="arrow-right" class="size-4" /></span>
                            <span class="mt-1 block font-bold group-hover:text-tertiary-strong">{{ $next->title }}</span>
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </article>

    @if ($related->isNotEmpty())
        <x-site.section background="muted">
            <x-site.heading heading="Related articles" />
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-site.post-card :post="$item" />
                @endforeach
            </div>
        </x-site.section>
    @endif

    {!! $closing->html !!}
@endsection
