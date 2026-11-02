{{-- Slim contact strip above the header (desktop only): service area, hours, email, phone and, on wide screens, social icons from Settings. --}}
@php
    use App\Support\Contact;
    use App\Support\Settings;

    $hours = Contact::hours()[0] ?? null;
    $radius = Settings::get('general.service_radius_text');
@endphp
<div class="hidden bg-glow-navy text-sm text-primary/85 lg:block" data-theme="dark">
    <div class="mx-auto flex h-10 max-w-7xl items-center gap-6 px-8">
        <p class="mr-auto flex items-center gap-2 truncate">
            @if ($radius)
                <x-site.icon name="map-pin" class="size-4 shrink-0 text-tertiary" /> {{ $radius }}
            @endif
        </p>
        <ul class="flex shrink-0 items-center gap-6">
            @if ($hours)
                <li class="flex items-center gap-2"><x-site.icon name="clock" class="size-4 text-tertiary" />{{ $hours['days'] }}: {{ $hours['hours'] }}</li>
            @endif
            @if (Contact::email())
                <li><a href="mailto:{{ Contact::email() }}" class="flex items-center gap-2 hover:text-primary"><x-site.icon name="mail" class="size-4 text-tertiary" />{{ Contact::email() }}</a></li>
            @endif
            @if (Contact::phone())
                <li><a href="{{ Contact::telHref() }}" class="flex items-center gap-2 font-semibold text-primary hover:text-tertiary-soft" data-call-location="top_bar"><x-site.icon name="phone" class="size-4 text-tertiary" />{{ Contact::phone() }}</a></li>
            @endif
        </ul>
        <x-site.social-links compact class="hidden shrink-0 border-l border-white/20 pl-4 xl:flex" />
    </div>
</div>
