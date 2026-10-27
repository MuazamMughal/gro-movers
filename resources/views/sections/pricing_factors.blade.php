{{-- What affects the cost of a move. --}}
@php($dark = ($s['background'] ?? '') === 'dark')
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" :dark="$dark" />
    <dl class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($s['factors'] ?? [] as $factor)
            <div class="flex gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-tertiary/10 text-tertiary-strong"><x-site.icon :name="$factor['icon'] ?? 'tag'" class="size-6" /></span>
                <div>
                    <dt class="text-lg font-bold">{{ $factor['title'] }}</dt>
                    @if ($factor['text'] ?? null)
                        <dd @class(['mt-1', 'text-primary/80' => $dark, 'text-secondary-light' => ! $dark])>{{ $factor['text'] }}</dd>
                    @endif
                </div>
            </div>
        @endforeach
    </dl>
    @if ($s['note'] ?? null)
        <p @class(['mx-auto mt-10 max-w-3xl rounded-xl p-4 text-center font-medium', 'bg-white/10' => $dark, 'bg-tertiary-soft text-secondary' => ! $dark])>{{ $s['note'] }}</p>
    @endif
    @if (! empty($s['button']))
        <div class="mt-10 text-center"><x-site.button :link="$s['button']" size="lg" /></div>
    @endif
</x-site.section>
