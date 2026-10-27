{{-- Numbered "How it works" steps. --}}
@php
    $dark = ($s['background'] ?? '') === 'dark';
    // Even rows: 4 steps go 2 × 2 on tablets and 4 across on desktops (never 3 + 1).
    $stepCols = match (count($s['steps'] ?? [])) {
        0, 1 => 'md:grid-cols-1',
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        6 => 'md:grid-cols-3',
        default => 'md:grid-cols-3',
    };
@endphp
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" :dark="$dark" />
    <ol class="grid gap-8 {{ $stepCols }}">
        @foreach ($s['steps'] ?? [] as $i => $step)
            <li class="relative">
                <span class="flex size-12 items-center justify-center rounded-full bg-energy glow-energy text-xl font-extrabold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                <h3 class="mt-4 text-xl font-bold"><span class="sr-only">Step {{ $i + 1 }}: </span>{{ $step['title'] }}</h3>
                @if ($step['text'] ?? null)
                    <p @class(['mt-2', 'text-primary/80' => $dark, 'text-secondary-light' => ! $dark])>{{ $step['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
    @if (! empty($s['button']))
        <div class="mt-12 text-center"><x-site.button :link="$s['button']" size="lg" /></div>
    @endif
</x-site.section>
