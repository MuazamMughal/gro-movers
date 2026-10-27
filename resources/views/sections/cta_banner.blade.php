{{-- Call-to-action banner: Get a Free Quote + Call Now. --}}
@php
    $theme = $s['theme'] ?? 'dark';
    $background = match ($theme) { 'accent' => 'accent', 'light' => 'muted', default => 'dark' };
@endphp
<x-site.section :background="$background" :anchor="$anchor" class="py-14! md:py-16!">
    <div class="flex flex-col items-center gap-6 text-center lg:flex-row lg:justify-between lg:text-left">
        <div class="max-w-2xl">
            <{{ $headingTag }} class="text-3xl font-extrabold tracking-tight text-balance">{{ $s['heading'] }}</{{ $headingTag }}>
            @if ($s['text'] ?? null)
                <p @class(['mt-3 text-lg', 'text-primary/85' => $theme === 'dark', 'text-white/90' => $theme === 'accent', 'text-secondary-light' => $theme === 'light'])>{{ $s['text'] }}</p>
            @endif
        </div>
        <div class="flex flex-wrap justify-center gap-3">
            <x-site.button :link="$s['button'] ?? ['label' => 'Get a Free Quote', 'url' => '/get-a-quote']" :variant="$theme === 'accent' ? 'light' : 'primary'" size="lg" />
            @if ($s['show_phone'] ?? true)
                <x-site.call-button :variant="$theme === 'light' ? 'outline' : 'outline-light'" size="lg" location="cta" />
            @endif
        </div>
    </div>
</x-site.section>
