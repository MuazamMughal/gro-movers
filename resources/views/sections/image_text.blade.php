{{-- Image beside text. --}}
@php($imageLeft = ($s['image_position'] ?? 'right') === 'left')
<x-site.section :background="$s['background'] ?? 'light'" :anchor="$anchor">
    <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
        <div @class(['lg:order-2' => $imageLeft])>
            <x-site.heading :eyebrow="$s['eyebrow'] ?? null" :heading="$s['heading'] ?? null" :tag="$headingTag" align="left" :dark="($s['background'] ?? '') === 'dark'" />
            @if ($s['content'] ?? null)
                <x-site.prose class="-mt-4">{!! $s['content'] !!}</x-site.prose>
            @endif
            @if (! empty($s['bullets']))
                <ul class="mt-6 space-y-3">
                    @foreach ($s['bullets'] as $bullet)
                        <li class="flex items-start gap-3 text-lg"><x-site.icon name="circle-check" class="mt-1 size-5 shrink-0 text-tertiary" />{{ $bullet['text'] }}</li>
                    @endforeach
                </ul>
            @endif
            <x-site.button :link="$s['button'] ?? null" class="mt-8" />
        </div>
        <div @class(['lg:order-1' => $imageLeft])>
            <x-picture :media="$s['image'] ?? null" sizes="(min-width: 1024px) 50vw, 100vw" class="block overflow-hidden rounded-2xl shadow-lg" img-class="h-auto w-full" />
        </div>
    </div>
</x-site.section>
