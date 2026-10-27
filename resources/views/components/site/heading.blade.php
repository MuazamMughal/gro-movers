@props(['eyebrow' => null, 'heading' => null, 'intro' => null, 'tag' => 'h2', 'align' => 'center', 'dark' => false])
{{-- Section heading block. The tag is h1 only for the page's first hero. --}}
@if ($eyebrow || $heading || $intro)
    <div @class([
        'mb-10 md:mb-14',
        'mx-auto max-w-3xl text-center' => $align === 'center',
        'max-w-3xl' => $align === 'left',
    ])>
        @if ($eyebrow)
            <p @class(['mb-3 text-sm font-semibold tracking-wider uppercase', 'text-tertiary-strong' => ! $dark, 'text-tertiary-soft' => $dark])>{{ $eyebrow }}</p>
        @endif
        @if ($heading)
            <{{ $tag }} class="text-3xl font-extrabold tracking-tight text-balance md:text-4xl">{{ $heading }}</{{ $tag }}>
        @endif
        @if ($intro)
            <p @class(['mt-4 text-lg text-pretty', 'text-secondary-light' => ! $dark, 'text-primary/80' => $dark])>{{ $intro }}</p>
        @endif
    </div>
@endif
