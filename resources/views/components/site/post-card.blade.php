@props(['post', 'featured' => false, 'headingTag' => 'h3'])
{{-- Blog post card: image, category, title, excerpt, date and reading time. --}}
@php
    $date = \App\Support\SiteTime::local($post->published_at);
@endphp
<article @class([
    'group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-primary transition-shadow hover:shadow-lg',
    'md:grid md:grid-cols-2' => $featured,
])>
    <div @class(['aspect-[1.91/1] overflow-hidden bg-muted', 'md:aspect-auto md:h-full' => $featured])>
        @if ($post->featuredImage)
            <x-picture :media="$post->featuredImage" :sizes="$featured ? '(min-width: 768px) 50vw, 100vw' : '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw'" :eager="$featured" img-class="size-full object-cover transition-transform duration-300 group-hover:scale-105" alt="" />
        @else
            <div class="flex size-full items-center justify-center text-secondary/30"><x-site.icon name="newspaper" class="size-12" /></div>
        @endif
    </div>
    <div @class(['flex flex-1 flex-col p-6', 'md:p-10 md:justify-center' => $featured])>
        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            @if ($featured)
                <span class="rounded-full bg-tertiary-strong px-2.5 py-0.5 text-xs font-bold tracking-wide text-white uppercase">Featured</span>
            @endif
            @if ($post->category)
                <span class="font-semibold text-tertiary-strong">{{ $post->category->name }}</span>
            @endif
        </div>
        <{{ $headingTag }} @class(['font-extrabold tracking-tight text-balance', 'text-2xl md:text-3xl' => $featured, 'text-xl' => ! $featured])>
            <a href="{{ $post->publicPath() }}" class="after:absolute after:inset-0 focus-visible:outline-none group-focus-within:underline">{{ $post->title }}</a>
        </{{ $headingTag }}>
        <p class="mt-3 flex-1 text-secondary-light">{{ $post->summary($featured ? 220 : 140) }}</p>
        <p class="mt-5 flex items-center gap-2 text-sm text-secondary-light">
            @if ($date)
                <time datetime="{{ $date->toIso8601String() }}">{{ $date->format('M j, Y') }}</time>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $post->reading_minutes }} min read</span>
        </p>
    </div>
</article>
