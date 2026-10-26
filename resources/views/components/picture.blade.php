<picture {{ $attributes->only('class') }}>
    @if ($webpSrcset)
        <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="{{ $sizes }}">
    @endif
    <img
        src="{{ $src }}"
        @if ($fallbackSrcset) srcset="{{ $fallbackSrcset }}" sizes="{{ $sizes }}" @endif
        alt="{{ $altText }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        @if ($eager) loading="eager" fetchpriority="high" @else loading="lazy" @endif
        decoding="async"
        @if ($imgClass) class="{{ $imgClass }}" @endif
    >
</picture>
