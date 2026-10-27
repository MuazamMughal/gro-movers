{{-- Trust bar: key numbers and badges. --}}
@php($dark = ($s['background'] ?? 'muted') === 'dark')
<x-site.section :background="$s['background'] ?? 'muted'" :anchor="$anchor" class="py-10! md:!py-12">
    @if ($s['heading'] ?? null)
        <{{ $headingTag }} class="mb-8 text-center text-xl font-bold">{{ $s['heading'] }}</{{ $headingTag }}>
    @endif
    @if (! empty($s['stats']))
        @php($statCols = match (min(count($s['stats']), 4)) { 1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', default => 'md:grid-cols-4' })
        <dl class="grid grid-cols-2 gap-6 text-center {{ $statCols }}">
            @foreach ($s['stats'] as $stat)
                <div>
                    <dt class="sr-only">{{ $stat['label'] }}</dt>
                    <dd class="text-3xl font-extrabold text-tertiary md:text-4xl">{{ $stat['value'] }}</dd>
                    <dd @class(['mt-1 text-sm font-medium', 'text-primary/80' => $dark, 'text-secondary-light' => ! $dark])>{{ $stat['label'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
    @if (! empty($s['badges']))
        <ul class="mt-8 flex flex-wrap items-center justify-center gap-x-8 gap-y-4 first:mt-0">
            @foreach ($s['badges'] as $badge)
                <li class="flex items-center gap-2 font-semibold">
                    <x-site.icon :name="$badge['icon'] ?? 'badge-check'" class="size-6 text-tertiary" />{{ $badge['label'] }}
                </li>
            @endforeach
        </ul>
    @endif
</x-site.section>
