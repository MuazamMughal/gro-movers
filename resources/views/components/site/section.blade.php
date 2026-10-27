@props(['background' => 'light', 'anchor' => null, 'width' => 'wide'])
{{-- Section wrapper: background theme, vertical rhythm and the content container. --}}
@php
    $theme = match ($background) {
        'dark' => 'bg-glow-navy text-primary',
        'muted' => 'bg-muted text-secondary',
        'accent' => 'bg-energy text-white',
        default => 'bg-primary text-secondary',
    };
    $container = $width === 'narrow' ? 'max-w-3xl' : 'max-w-7xl';
@endphp
<section @if ($anchor) id="{{ $anchor }}" @endif {{ $attributes->class([$theme, 'py-16 md:py-24']) }} data-theme="{{ $background }}">
    <div class="mx-auto {{ $container }} px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </div>
</section>
