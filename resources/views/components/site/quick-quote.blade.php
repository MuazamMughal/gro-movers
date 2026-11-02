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
    $cell = 'quick-quote-cell relative rounded-xl px-4 py-3 focus-within:bg-muted';
    $label = 'block text-sm font-semibold text-secondary-light';
    $input = 'mt-0.5 block w-full min-w-0 bg-transparent text-base font-semibold text-secondary placeholder:font-normal placeholder:text-secondary-light/70 focus:outline-none';
    $error = 'mt-1 text-sm font-medium text-red-700 empty:hidden';
@endphp
<form
    method="get"
    action="{{ QuoteLink::href() }}"
    data-island="quick-quote"
    data-address-api="{{ Settings::get('integrations.address_api_url') }}"
    novalidate
    {{ $attributes->class('quick-quote relative z-10 grid gap-1 rounded-2xl bg-white p-2 text-left shadow-2xl ring-1 ring-black/5 md:grid-cols-2 lg:grid-cols-[1.3fr_1.3fr_0.85fr_0.95fr_auto]') }}
>
    @foreach (['from' => ['Moving from', 'map-pin'], 'to' => ['Moving to', 'map-pin-check']] as $side => [$text, $icon])
        <div class="{{ $cell }}" data-stop="{{ $side }}">
            <label for="quick-{{ $side }}" class="{{ $label }} flex items-center gap-1.5"><x-site.icon :name="$icon" class="size-4 text-tertiary-strong" />{{ $text }}</label>
            <input id="quick-{{ $side }}" data-field="{{ $side }}" type="text" autocomplete="off" maxlength="255" placeholder="Address, city or ZIP" aria-describedby="quick-{{ $side }}-error" class="{{ $input }}">
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
        <select id="quick-type" data-field="type" aria-describedby="quick-type-error" class="{{ $input }}">
            <option value="">Select…</option>
            @foreach ($moveTypes as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>
        <p id="quick-type-error" class="{{ $error }}" data-error-for-field="type"></p>
    </div>
    <button type="submit" class="inline-flex min-h-14 items-center justify-center rounded-xl btn-energy px-7 text-lg font-bold whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tertiary md:col-span-2 lg:col-span-1">Get a Free Quote</button>
</form>
