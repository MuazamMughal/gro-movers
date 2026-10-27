{{-- Google Map embed (lazy-loaded iframe). Only https://www.google.com/maps/embed URLs are used. --}}
@php
    $url = $s['embed_url'] ?? null;
    $valid = $url && preg_match('#^https://(www\.)?google\.[a-z.]+/maps/embed\?#i', $url);
    $address = \App\Support\Contact::address();
    $src = $valid ? $url : ($address ? 'https://www.google.com/maps?q='.urlencode($address).'&output=embed' : null);
    $height = match ($s['height'] ?? 'md') { 'sm' => 'h-64', 'lg' => 'h-[32rem]', default => 'h-96' };
@endphp
@if ($src)
<x-site.section background="light" :anchor="$anchor" class="py-12!">
    @if ($s['heading'] ?? null)
        <{{ $headingTag }} class="mb-6 text-2xl font-extrabold">{{ $s['heading'] }}</{{ $headingTag }}>
    @endif
    <iframe src="{{ $src }}" title="Map: {{ $s['heading'] ?? $address }}" class="{{ $height }} w-full rounded-2xl border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
</x-site.section>
@endif
