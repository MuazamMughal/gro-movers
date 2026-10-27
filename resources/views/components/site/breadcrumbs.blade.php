@props(['items', 'current' => null])
{{-- Breadcrumb trail plus its BreadcrumbList JSON-LD. The last item is the current page ($current = its canonical URL). --}}
<nav aria-label="Breadcrumb" class="border-b border-border bg-muted/60">
    <ol class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-1.5 gap-y-1 px-4 py-2.5 text-sm text-secondary-light sm:px-6 lg:px-8">
        @foreach ($items as $crumb)
            <li class="flex items-center gap-1.5">
                @unless ($loop->first)
                    <x-site.icon name="chevron-right" class="size-3.5 shrink-0 opacity-60" />
                @endunless
                @if ($crumb['url'] !== null && ! $loop->last)
                    <a href="{{ $crumb['url'] }}" class="hover:text-tertiary hover:underline">{{ $crumb['label'] }}</a>
                @else
                    <span class="line-clamp-1 font-semibold text-secondary" aria-current="page">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
<script type="application/ld+json">{!! json_encode(\App\Support\Breadcrumbs::schema($items, $current), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
