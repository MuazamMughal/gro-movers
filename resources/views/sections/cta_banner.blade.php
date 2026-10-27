{{--
    Call-to-action banner: Get a Free Quote + Call Now. Always the light style
    (as on About Us), so every page ends the same way above the navy footer.
--}}
<x-site.section background="muted" :anchor="$anchor" class="border-t border-border py-14! md:py-16!">
    <div class="flex flex-col items-center gap-6 text-center lg:flex-row lg:justify-between lg:text-left">
        <div class="max-w-2xl">
            <{{ $headingTag }} class="text-3xl font-extrabold tracking-tight text-balance">{{ $s['heading'] }}</{{ $headingTag }}>
            @if ($s['text'] ?? null)
                <p class="mt-3 text-lg text-secondary-light">{{ $s['text'] }}</p>
            @endif
        </div>
        <div class="flex flex-wrap justify-center gap-3">
            <x-site.button :link="$s['button'] ?? ['label' => 'Get a Free Quote', 'url' => '/get-a-quote']" size="lg" />
            @if ($s['show_phone'] ?? true)
                <x-site.call-button variant="outline" size="lg" location="cta" />
            @endif
        </div>
    </div>
</x-site.section>
