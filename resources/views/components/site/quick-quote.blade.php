@props(['moveTypes' => null])
{{--
    First step of a quote, for the home hero: Moving From, Moving To, Moving Date and Type
    of Move. The "quick-quote" island keeps the answers in this browser tab and opens the
    quote page, which asks for the rest. The inputs have no names on purpose, so addresses
    never end up in a URL; without JS the button simply opens the full form.
--}}
@php
    use App\Support\QuoteLink;
    use App\Support\Settings;
    use App\Support\SiteTime;

    $moveTypes ??= \App\Models\MoveType::query()->active()->ordered()->get();
    $today = SiteTime::local(now())->toDateString();
    $latest = SiteTime::local(now())->addMonths(18)->toDateString();
    $cell = 'p-2';
    $label = 'mb-1.5 block text-sm font-semibold text-secondary';
    $input = 'block h-12 w-full min-w-0 rounded-lg border border-secondary/25 bg-white px-3 text-base font-medium text-secondary shadow-xs transition-colors placeholder:font-normal placeholder:text-secondary-light/70 hover:border-secondary/45 focus:border-tertiary focus:ring-2 focus:ring-tertiary/30 focus:outline-none aria-invalid:border-red-600';
    $error = 'mt-1 text-sm font-medium text-red-700 empty:hidden';
@endphp
<form
    method="get"
    action="{{ QuoteLink::href() }}"
    data-island="quick-quote"
    data-address-api="{{ Settings::get('integrations.address_api_url') }}"
    novalidate
    {{ $attributes->class('quick-quote relative z-10 grid rounded-2xl bg-white p-2 text-left shadow-2xl ring-1 ring-black/5 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-[1.3fr_1.3fr_0.85fr_0.95fr_auto]') }}
>
    @foreach (['from' => ['Moving from', 'map-pin'], 'to' => ['Moving to', 'map-pin-check']] as $side => [$text, $icon])
        <div class="{{ $cell }}">
            <label for="quick-{{ $side }}" class="{{ $label }}">{{ $text }}</label>
            <div class="relative">
                <x-site.icon :name="$icon" class="pointer-events-none absolute top-3.5 left-3 size-5 text-tertiary-strong" />
                <input id="quick-{{ $side }}" data-field="{{ $side }}" type="text" autocomplete="off" maxlength="255" placeholder="Address, city or ZIP" aria-describedby="quick-{{ $side }}-error" class="{{ $input }} pl-10">
            </div>
            <p id="quick-{{ $side }}-error" class="{{ $error }}" data-error-for-field="{{ $side }}"></p>
        </div>
    @endforeach
    <div class="{{ $cell }}">
        <label for="quick-date" class="{{ $label }}">Moving date</label>
        <input id="quick-date" data-field="date" type="date" min="{{ $today }}" max="{{ $latest }}" aria-describedby="quick-date-error" class="{{ $input }}">
        <p id="quick-date-error" class="{{ $error }}" data-error-for-field="date"></p>
    </div>
    <div class="{{ $cell }}">
        <label for="quick-type" class="{{ $label }}">Type of move</label>
        <div class="relative">
            <select id="quick-type" data-field="type" aria-describedby="quick-type-error" class="{{ $input }} appearance-none pr-9">
                <option value="">Select…</option>
                @foreach ($moveTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
            <x-site.icon name="chevron-down" class="pointer-events-none absolute top-4 right-3 size-4 text-secondary-light" />
        </div>
        <p id="quick-type-error" class="{{ $error }}" data-error-for-field="type"></p>
    </div>
    <button type="submit" class="m-2 inline-flex h-12 items-center justify-center rounded-lg btn-energy px-6 text-lg font-bold whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tertiary md:col-span-2 lg:col-span-4 xl:col-span-1 xl:mt-[2.125rem]">Get a Free Quote</button>
</form>
