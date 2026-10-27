{{--
    Site footer: logo and blurb, the three footer menu columns, contact details (NAP),
    USDOT/MC numbers, social links, legal links and copyright.
--}}
@php
    use App\Enums\MenuLocation;
    use App\Enums\SystemPage;
    use App\Models\Page;
    use App\Support\Contact;
    use App\Support\Menus;
    use App\Support\Settings;

    $company = Settings::get('general.company_name');
    $logo = Settings::get('branding.logo_dark_id');
    $columns = collect([MenuLocation::Footer1, MenuLocation::Footer2, MenuLocation::Footer3])
        ->map(fn ($location) => ['title' => Menus::title($location), 'items' => Menus::for($location)])
        ->filter(fn ($column) => $column['items'] !== []);
    $social = collect((array) Settings::get('general.social', []))->filter()
        ->only(['facebook', 'instagram', 'youtube', 'linkedin', 'google', 'yelp']);
    $socialLabels = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'google' => 'Google', 'yelp' => 'Yelp'];
    $socialIcons = ['facebook' => 'facebook', 'instagram' => 'instagram', 'youtube' => 'youtube', 'linkedin' => 'linkedin', 'google' => 'star', 'yelp' => 'star'];
    $legal = collect([SystemPage::Privacy, SystemPage::Terms])
        ->map(fn ($key) => Page::system($key))
        ->filter(fn ($page) => $page?->isPublished());
    $usdot = Settings::get('general.usdot_number');
    $mc = Settings::get('general.mc_number');
@endphp
<footer class="bg-secondary text-primary" data-theme="dark">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.4fr_repeat(4,1fr)] lg:px-8">
        <div class="space-y-4">
            <a href="/" class="inline-block" aria-label="{{ $company }} home">
                @if ($logo)
                    <x-picture :media="$logo" sizes="220px" :alt="$company" img-class="h-12 w-auto max-w-[220px] object-contain" />
                @else
                    <span class="text-xl font-extrabold tracking-tight">{{ $company }}</span>
                @endif
            </a>
            @if ($blurb = Settings::get('general.footer_blurb'))
                <p class="text-primary/75">{{ $blurb }}</p>
            @endif
            @if ($social->isNotEmpty())
                <ul class="flex gap-2" aria-label="Social media">
                    @foreach ($social as $network => $url)
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="flex size-10 items-center justify-center rounded-full bg-white/10 hover:bg-tertiary" aria-label="{{ $company }} on {{ $socialLabels[$network] }}">
                                <x-site.icon :name="$socialIcons[$network]" class="size-5" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @foreach ($columns as $column)
            <nav aria-label="{{ $column['title'] ?: 'Footer' }}">
                @if ($column['title'])
                    <p class="mb-4 font-bold tracking-wide uppercase">{{ $column['title'] }}</p>
                @endif
                <ul class="space-y-2.5">
                    @foreach ($column['items'] as $item)
                        @if ($item['url'])
                            <li><a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif class="text-primary/75 hover:text-tertiary-soft">{{ $item['label'] }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </nav>
        @endforeach

        <div class="lg:col-start-5">
            <p class="mb-4 font-bold tracking-wide uppercase">Contact</p>
            <address class="space-y-2.5 text-primary/75 not-italic">
                <p class="flex gap-2"><x-site.icon name="map-pin" class="mt-0.5 size-5 shrink-0 text-tertiary" /><span>{{ Contact::address() }}</span></p>
                @if (Contact::phone())
                    <p class="flex gap-2"><x-site.icon name="phone" class="mt-0.5 size-5 shrink-0 text-tertiary" /><a href="{{ Contact::telHref() }}" class="font-semibold text-primary hover:text-tertiary-soft" data-call-location="footer">{{ Contact::phone() }}</a></p>
                @endif
                @if (Contact::email())
                    <p class="flex gap-2"><x-site.icon name="mail" class="mt-0.5 size-5 shrink-0 text-tertiary" /><a href="mailto:{{ Contact::email() }}" class="hover:text-tertiary-soft">{{ Contact::email() }}</a></p>
                @endif
                @foreach (Contact::hours() as $row)
                    <p class="flex gap-2 text-sm"><x-site.icon name="clock" class="mt-0.5 size-4 shrink-0 text-tertiary" /><span><span class="font-semibold text-primary">{{ $row['days'] }}:</span> {{ $row['hours'] }}</span></p>
                @endforeach
            </address>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm text-primary/70 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
            <p>
                © {{ now()->year }} {{ $company }}. All rights reserved.
                @if ($usdot) <span class="ml-2">USDOT #{{ $usdot }}</span> @endif
                @if ($mc) <span class="ml-2">{{ str_starts_with(strtoupper($mc), 'MC') ? strtoupper($mc) : 'MC #'.$mc }}</span> @endif
            </p>
            @if ($legal->isNotEmpty())
                <ul class="flex gap-5">
                    @foreach ($legal as $page)
                        <li><a href="{{ $page->publicPath() }}" class="hover:text-tertiary-soft">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</footer>
