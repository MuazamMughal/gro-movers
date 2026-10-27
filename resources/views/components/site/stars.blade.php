@props(['rating' => 5])
{{-- Star rating, e.g. 4.8 out of 5. --}}
<span {{ $attributes->class('inline-flex items-center gap-0.5 text-tertiary') }} role="img" aria-label="{{ rtrim(rtrim(number_format((float) $rating, 1), '0'), '.') }} out of 5 stars">
    @for ($i = 1; $i <= 5; $i++)
        <svg viewBox="0 0 20 20" class="size-4 {{ $i <= round($rating) ? 'fill-current' : 'fill-current opacity-25' }}" aria-hidden="true"><path d="M10 1.5l2.6 5.3 5.9.9-4.25 4.1 1 5.85L10 14.9l-5.25 2.75 1-5.85L1.5 7.7l5.9-.9z"/></svg>
    @endfor
</span>
