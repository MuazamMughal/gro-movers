@props(['paginator', 'basePath'])
{{-- Path-based pagination: page 1 is the base path, then {base}/page/N. --}}
@php
    $url = fn (int $n) => $n === 1 ? $basePath : rtrim($basePath, '/')."/page/{$n}";
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = collect(range(max(1, $current - 2), min($last, $current + 2)));
    $link = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-border px-3 font-semibold hover:border-tertiary hover:text-tertiary-strong';
@endphp
@if ($last > 1)
    <nav class="mt-14 flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">
        @if ($current > 1)
            <a href="{{ $url($current - 1) }}" class="{{ $link }}" rel="prev">← <span class="ml-1 hidden sm:inline">Newer</span></a>
        @endif
        @if ($pages->first() > 1)
            <a href="{{ $url(1) }}" class="{{ $link }}">1</a>
            @if ($pages->first() > 2)<span class="px-1 text-secondary-light" aria-hidden="true">…</span>@endif
        @endif
        @foreach ($pages as $n)
            @if ($n === $current)
                <span class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-secondary px-3 font-semibold text-primary" aria-current="page">{{ $n }}</span>
            @else
                <a href="{{ $url($n) }}" class="{{ $link }}">{{ $n }}</a>
            @endif
        @endforeach
        @if ($pages->last() < $last)
            @if ($pages->last() < $last - 1)<span class="px-1 text-secondary-light" aria-hidden="true">…</span>@endif
            <a href="{{ $url($last) }}" class="{{ $link }}">{{ $last }}</a>
        @endif
        @if ($current < $last)
            <a href="{{ $url($current + 1) }}" class="{{ $link }}" rel="next"><span class="mr-1 hidden sm:inline">Older</span> →</a>
        @endif
    </nav>
@endif
