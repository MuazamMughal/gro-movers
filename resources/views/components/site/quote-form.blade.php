@props(['moveTypes' => collect(), 'dark' => false, 'id' => 'quote-form'])
{{--
    Quote request form: exactly the agreed fields. Works as a plain POST without JS;
    T12 adds the Vue island (Google Places autocomplete, inline validation, submit).
--}}
@php
    $inputClass = 'block w-full rounded-lg border border-border bg-white px-4 py-3 text-base text-secondary placeholder:text-secondary-light/70 focus:border-tertiary focus:ring-2 focus:ring-tertiary/30 focus:outline-none';
    $labelClass = 'mb-1.5 block text-sm font-semibold text-secondary';
    $today = now()->toDateString();
@endphp
<form
    id="{{ $id }}"
    method="post"
    action="/forms/quote"
    data-island="quote-form"
    novalidate
    {{ $attributes->class('rounded-2xl bg-white p-6 text-secondary shadow-xl ring-1 ring-black/5 sm:p-8') }}
>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="{{ $id }}-name" class="{{ $labelClass }}">Full Name</label>
            <input id="{{ $id }}-name" name="full_name" type="text" required autocomplete="name" maxlength="100" class="{{ $inputClass }}">
        </div>
        <div>
            <label for="{{ $id }}-email" class="{{ $labelClass }}">Email Address</label>
            <input id="{{ $id }}-email" name="email" type="email" required autocomplete="email" inputmode="email" class="{{ $inputClass }}">
        </div>
        <div>
            <label for="{{ $id }}-phone" class="{{ $labelClass }}">Phone Number</label>
            <input id="{{ $id }}-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" placeholder="(555) 555-5555" class="{{ $inputClass }}">
        </div>
        <div>
            <label for="{{ $id }}-from" class="{{ $labelClass }}">Moving From</label>
            <input id="{{ $id }}-from" name="from_address" type="text" required autocomplete="off" placeholder="Address, city or ZIP" class="{{ $inputClass }}" data-places="from">
        </div>
        <div>
            <label for="{{ $id }}-to" class="{{ $labelClass }}">Moving To</label>
            <input id="{{ $id }}-to" name="to_address" type="text" required autocomplete="off" placeholder="Address, city or ZIP" class="{{ $inputClass }}" data-places="to">
        </div>
        <div>
            <label for="{{ $id }}-date" class="{{ $labelClass }}">Moving Date</label>
            <input id="{{ $id }}-date" name="move_date" type="date" required min="{{ $today }}" class="{{ $inputClass }}">
        </div>
        <div>
            <label for="{{ $id }}-type" class="{{ $labelClass }}">Type of Move</label>
            <select id="{{ $id }}-type" name="move_type_id" required class="{{ $inputClass }}">
                <option value="">Select…</option>
                @foreach ($moveTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Honeypot: hidden from people, filled in by bots. --}}
    <div class="absolute -left-[9999px]" aria-hidden="true">
        <label for="{{ $id }}-website">Website</label>
        <input id="{{ $id }}-website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>

    <button type="submit" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-tertiary px-6 py-3 text-lg font-bold text-white transition-colors hover:bg-tertiary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tertiary">
        {{ \App\Support\Settings::get('forms.quote_button_label', 'Submit Request') }}
    </button>

    @if ($tcpa = \App\Support\Settings::get('forms.tcpa_text'))
        <p class="mt-4 text-xs leading-relaxed text-secondary-light">{{ str_replace('{company}', \App\Support\Settings::get('general.company_name'), $tcpa) }}</p>
    @endif
</form>
