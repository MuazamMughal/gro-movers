@props(['link' => null, 'href' => null, 'label' => null, 'variant' => 'primary', 'size' => 'md'])
{{-- Button link. Pass a section `link` field ({label, url, new_tab}) or href + label. --}}
@php
    $href ??= $link['url'] ?? null;
    $label ??= $link['label'] ?? null;
    $newTab = (bool) ($link['new_tab'] ?? false);
    $classes = [
        'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tertiary',
        'min-h-11 px-5 py-2.5 text-base' => $size === 'md',
        'min-h-12 px-7 py-3 text-lg' => $size === 'lg',
        'bg-tertiary-strong text-white hover:bg-tertiary-hover' => $variant === 'primary',
        'border-2 border-secondary text-secondary hover:bg-muted' => $variant === 'outline',
        'border-2 border-primary/70 text-primary hover:bg-primary hover:text-secondary' => $variant === 'outline-light',
        'bg-primary text-secondary hover:bg-muted' => $variant === 'light',
    ];
@endphp
@if ($href && $label)
    <a href="{{ $href }}" {{ $attributes->class($classes) }} @if ($newTab) target="_blank" rel="noopener" @endif>{{ $slot->isEmpty() ? '' : $slot }}{{ $label }}</a>
@endif
