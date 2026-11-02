@props(['moveTypes' => null, 'id' => 'quote', 'old' => [], 'errors' => null])
{{--
    Quote request form: exactly Full Name, Email Address, Phone Number, Moving From,
    Moving To, Moving Date, Type of Move and the submit button. A plain POST works
    without JS; the "quote-form" island adds Photon address suggestions, inline validation and AJAX submit.
    $old / $errors refill the form after a failed no-JS submit (forms/invalid).
--}}
@php
    use App\Support\Settings;
    use App\Support\SiteTime;

    $moveTypes ??= \App\Models\MoveType::query()->active()->ordered()->get();
    $inputClass = 'block w-full rounded-lg border border-border bg-white px-4 py-3 text-base text-secondary placeholder:text-secondary-light/70 focus:border-tertiary focus:ring-2 focus:ring-tertiary/30 focus:outline-none aria-invalid:border-red-600';
    $labelClass = 'mb-1.5 block text-sm font-semibold text-secondary';
    $today = SiteTime::local(now())->toDateString();
    $latest = SiteTime::local(now())->addMonths(18)->toDateString();
    $invalid = fn (string $field) => $errors?->has($field) ? 'true' : null;
    $old = (array) $old;
@endphp
<form
    id="{{ $id }}"
    method="post"
    action="/forms/quote"
    data-island="quote-form"
    data-address-api="{{ Settings::get('integrations.address_api_url') }}"
    novalidate
    {{ $attributes->class('relative rounded-2xl bg-white p-6 text-secondary shadow-xl ring-1 ring-black/5 sm:p-8') }}
>
    @if (\App\Support\QuoteLink::claimAnchor())
        {{-- "Get a Free Quote" links jump here; the offset keeps the form clear of the sticky header. --}}
        <span id="{{ \App\Support\QuoteLink::ANCHOR }}" class="pointer-events-none absolute -top-28 left-0" aria-hidden="true"></span>
    @endif
    <p class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-800 empty:hidden" role="alert" data-form-error>{{ $errors?->first('form') }}</p>

    {{-- Set before the form paints, so answers from the hero's first step never flash as empty fields. --}}
    <script>try{if(sessionStorage.getItem('quoteDraft'))document.currentScript.parentElement.setAttribute('data-draft','1')}catch(e){}</script>

    <div class="move-summary mb-5 rounded-xl border border-border bg-muted p-4" data-move-summary>
        <div class="flex items-start justify-between gap-4">
            <p class="font-bold">Your move</p>
            <button type="button" class="text-sm font-semibold text-tertiary-strong underline underline-offset-2 hover:no-underline" data-move-edit>Edit</button>
        </div>
        <dl class="mt-2 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div><dt class="text-secondary-light">From</dt><dd class="font-semibold" data-summary="from"></dd></div>
            <div><dt class="text-secondary-light">To</dt><dd class="font-semibold" data-summary="to"></dd></div>
            <div><dt class="text-secondary-light">Date</dt><dd class="font-semibold" data-summary="when"></dd></div>
            <div><dt class="text-secondary-light">Type of move</dt><dd class="font-semibold" data-summary="type"></dd></div>
        </dl>
        <p class="mt-3 text-sm text-secondary-light">Just your contact details left.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="{{ $id }}-name" class="{{ $labelClass }}">Full Name</label>
            <input id="{{ $id }}-name" name="full_name" type="text" required autocomplete="name" minlength="2" maxlength="100" value="{{ $old['full_name'] ?? '' }}" aria-invalid="{{ $invalid('full_name') }}" aria-describedby="{{ $id }}-name-error" class="{{ $inputClass }}">
            <x-site.form-error field="full_name" :id="$id.'-name'" :errors="$errors" />
        </div>
        <div>
            <label for="{{ $id }}-email" class="{{ $labelClass }}">Email Address</label>
            <input id="{{ $id }}-email" name="email" type="email" required autocomplete="email" inputmode="email" maxlength="150" value="{{ $old['email'] ?? '' }}" aria-invalid="{{ $invalid('email') }}" aria-describedby="{{ $id }}-email-error" class="{{ $inputClass }}">
            <x-site.form-error field="email" :id="$id.'-email'" :errors="$errors" />
        </div>
        <div>
            <label for="{{ $id }}-phone" class="{{ $labelClass }}">Phone Number</label>
            <input id="{{ $id }}-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" maxlength="30" placeholder="(555) 555-5555" value="{{ $old['phone'] ?? '' }}" aria-invalid="{{ $invalid('phone') }}" aria-describedby="{{ $id }}-phone-error" class="{{ $inputClass }}">
            <x-site.form-error field="phone" :id="$id.'-phone'" :errors="$errors" />
        </div>
        @foreach (['from' => 'Moving From', 'to' => 'Moving To'] as $side => $label)
            <div data-address="{{ $side }}" data-move-field>
                <label for="{{ $id }}-{{ $side }}" class="{{ $labelClass }}">{{ $label }}</label>
                <div class="relative">
                    <x-site.icon :name="$side === 'from' ? 'map-pin' : 'map-pin-check'" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-tertiary-strong" />
                    <input id="{{ $id }}-{{ $side }}" name="{{ $side }}_address" type="text" required autocomplete="off" minlength="5" maxlength="255" placeholder="Address, city or ZIP" value="{{ $old[$side.'_address'] ?? '' }}" aria-invalid="{{ $invalid($side.'_address') }}" aria-describedby="{{ $id }}-{{ $side }}-error" class="{{ $inputClass }} pl-11">
                </div>
                @foreach (\App\Http\Requests\Forms\QuoteRequest::ADDRESS_PARTS as $part)
                    <input type="hidden" name="{{ $side }}_{{ $part }}" value="{{ $old[$side.'_'.$part] ?? '' }}">
                @endforeach
                <x-site.form-error :field="$side.'_address'" :id="$id.'-'.$side" :errors="$errors" />
            </div>
        @endforeach
        <div data-move-field>
            <label for="{{ $id }}-date" class="{{ $labelClass }}">Moving Date</label>
            <input id="{{ $id }}-date" name="move_date" type="date" required min="{{ $today }}" max="{{ $latest }}" value="{{ $old['move_date'] ?? '' }}" aria-invalid="{{ $invalid('move_date') }}" aria-describedby="{{ $id }}-date-error" class="{{ $inputClass }}">
            <x-site.form-error field="move_date" :id="$id.'-date'" :errors="$errors" />
        </div>
        <div data-move-field>
            <label for="{{ $id }}-type" class="{{ $labelClass }}">Type of Move</label>
            <select id="{{ $id }}-type" name="move_type_id" required aria-invalid="{{ $invalid('move_type_id') }}" aria-describedby="{{ $id }}-type-error" class="{{ $inputClass }}">
                <option value="">Select…</option>
                @foreach ($moveTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) ($old['move_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            <x-site.form-error field="move_type_id" :id="$id.'-type'" :errors="$errors" />
        </div>
    </div>

    <x-site.form-guard form="quote" :old="$old" challenge-class="data-visible:mt-5" />

    <button type="submit" class="mt-6 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg btn-energy px-6 py-3 text-lg font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tertiary disabled:cursor-wait disabled:opacity-70">
        {{ Settings::get('forms.quote_button_label', 'Submit Request') }}
    </button>

    @if ($tcpa = Settings::get('forms.tcpa_text'))
        <p class="mt-4 text-xs leading-relaxed text-secondary-light">{{ str_replace('{company}', Settings::get('general.company_name'), $tcpa) }}</p>
    @endif
</form>
