{{-- Free text. --}}
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor" :width="($s['width'] ?? 'narrow') === 'narrow' ? 'narrow' : 'wide'">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :tag="$headingTag" align="left" :dark="($s['background'] ?? '') === 'dark'" />
    <x-site.prose>{!! $s['content'] ?? '' !!}</x-site.prose>
</x-site.section>
