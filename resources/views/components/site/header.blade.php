{{--
    Site header: logo, header menu (one level of dropdowns), Call Now and Get a Free Quote.
    Dropdowns open on hover and keyboard focus without JS; the small script adds
    click-to-open, aria-expanded and Escape. Phones get a <details> drawer (mobile menu).
    It sticks to the top on scroll (the contact bar above it scrolls away).
--}}
@php
    use App\Enums\MenuLocation;
    use App\Support\Menus;
    use App\Support\Settings;

    $items = Menus::for(MenuLocation::Header);
    $mobileItems = Menus::mobile();
    $logo = Settings::get('branding.logo_light_id');
    $company = Settings::get('general.company_name');
    $current = '/'.ltrim(request()->path(), '/');
    $isCurrent = fn (?string $url) => $url !== null && rtrim(strtok($url, '#'), '/') === rtrim($current, '/');
@endphp
<header class="sticky top-0 z-40 border-b border-border bg-primary/95 backdrop-blur supports-[backdrop-filter]:bg-primary/90">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-secondary focus:px-4 focus:py-2 focus:text-primary">Skip to content</a>
    <div class="mx-auto flex h-18 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
        <a href="/" class="flex shrink-0 items-center" aria-label="{{ $company }} home">
            @if ($logo)
                <x-picture :media="$logo" sizes="(min-width: 640px) 200px, 160px" eager :alt="$company" img-class="h-10 w-auto max-w-[200px] object-contain sm:h-12" />
            @else
                <span class="text-xl font-extrabold tracking-tight">{{ $company }}</span>
            @endif
        </a>

        @if ($items)
            <nav class="hidden flex-1 justify-center lg:flex" aria-label="Main">
                <ul class="flex items-center gap-1">
                    @foreach ($items as $item)
                        @if ($item['children'])
                            <li class="group relative" data-dropdown>
                                <div class="flex items-center">
                                    @if ($item['url'])
                                        <a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif @if ($isCurrent($item['url'])) aria-current="page" @endif class="rounded-lg py-2 pr-1 pl-3 font-semibold hover:text-tertiary-strong aria-[current=page]:text-tertiary-strong">{{ $item['label'] }}</a>
                                        <button type="button" class="rounded-lg p-2 hover:text-tertiary-strong" aria-expanded="false" aria-label="{{ $item['label'] }} menu" data-dropdown-toggle>
                                            <x-site.icon name="chevron-down" class="size-4 transition-transform group-data-open:rotate-180" />
                                        </button>
                                    @else
                                        <button type="button" class="flex items-center gap-1 rounded-lg px-3 py-2 font-semibold hover:text-tertiary-strong" aria-expanded="false" data-dropdown-toggle>
                                            {{ $item['label'] }} <x-site.icon name="chevron-down" class="size-4 transition-transform group-data-open:rotate-180" />
                                        </button>
                                    @endif
                                </div>
                                <ul class="invisible absolute top-full left-0 z-50 min-w-60 translate-y-1 rounded-xl border border-border bg-primary p-2 opacity-0 shadow-xl transition group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-data-open:visible group-data-open:opacity-100">
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}" @if ($child['new_tab']) target="_blank" rel="noopener" @endif @if ($isCurrent($child['url'])) aria-current="page" @endif class="block rounded-lg px-3 py-2 hover:bg-muted hover:text-tertiary-strong aria-[current=page]:text-tertiary-strong">{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li>
                                <a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif @if ($isCurrent($item['url'])) aria-current="page" @endif class="block rounded-lg px-3 py-2 font-semibold hover:text-tertiary-strong aria-[current=page]:text-tertiary-strong">{{ $item['label'] }}</a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="ml-auto flex items-center gap-2 lg:ml-0">
            {{-- Call Now is always visible: below 768px in the mobile bar, then here (compact while the menu needs the room). --}}
            <x-site.call-button variant="outline" location="header" class="hidden! md:inline-flex! lg:hidden! xl:inline-flex!" />
            <x-site.call-button variant="outline" location="header" compact class="hidden! lg:inline-flex! xl:hidden!" />
            <x-site.button :href="\App\Support\QuoteLink::href()" label="Get a Free Quote" class="hidden! sm:inline-flex!" />

            @if ($mobileItems)
                <details class="group/drawer lg:hidden" data-drawer>
                    <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-lg border border-border [&::-webkit-details-marker]:hidden" aria-label="Menu">
                        <x-site.icon name="menu" class="size-6 group-open/drawer:hidden" />
                        <x-site.icon name="x" class="hidden size-6 group-open/drawer:block" />
                    </summary>
                    <nav class="absolute inset-x-0 top-full max-h-[calc(100dvh-4.5rem)] overflow-y-auto border-b border-border bg-primary px-4 pt-2 pb-6 shadow-xl sm:px-6" aria-label="Mobile">
                        <ul class="divide-y divide-border">
                            @foreach ($mobileItems as $item)
                                <li>
                                    @if ($item['children'])
                                        <details class="group/sub">
                                            <summary class="flex cursor-pointer list-none items-center justify-between py-3 text-lg font-semibold [&::-webkit-details-marker]:hidden">
                                                {{ $item['label'] }} <x-site.icon name="chevron-down" class="size-5 transition-transform group-open/sub:rotate-180" />
                                            </summary>
                                            <ul class="pb-3 pl-3">
                                                @if ($item['url'])
                                                    <li><a href="{{ $item['url'] }}" class="block py-2 font-medium text-tertiary-strong">All {{ Str::lower($item['label']) }}</a></li>
                                                @endif
                                                @foreach ($item['children'] as $child)
                                                    <li><a href="{{ $child['url'] }}" @if ($child['new_tab']) target="_blank" rel="noopener" @endif class="block py-2">{{ $child['label'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @else
                                        <a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif class="block py-3 text-lg font-semibold">{{ $item['label'] }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4 grid gap-3 sm:hidden">
                            <x-site.button :href="\App\Support\QuoteLink::href()" label="Get a Free Quote" size="lg" />
                            <x-site.call-button variant="outline" size="lg" location="mobile_menu" />
                        </div>
                    </nav>
                </details>
            @endif
        </div>
    </div>
</header>

@once
    @push('body-end')
        <script>
            // Click to open dropdowns (hover and focus already work without JS), Escape to close.
            document.querySelectorAll('[data-dropdown]').forEach((li) => {
                const button = li.querySelector('[data-dropdown-toggle]');
                const set = (open) => { li.toggleAttribute('data-open', open); button.setAttribute('aria-expanded', open); };
                button.addEventListener('click', () => set(!li.hasAttribute('data-open')));
                li.addEventListener('mouseleave', () => set(false));
                li.addEventListener('keydown', (e) => { if (e.key === 'Escape') { set(false); button.focus(); } });
                document.addEventListener('click', (e) => { if (!li.contains(e.target)) set(false); });
            });
        </script>
    @endpush
@endonce
