@props(['id' => 'contact-form', 'old' => [], 'errors' => null])
{{--
    Contact form: name, email, phone (optional), message. A plain POST works without JS;
    the "contact-form" island adds inline validation and AJAX submit.
--}}
@php
    $inputClass = 'block w-full rounded-lg border border-border bg-white px-4 py-3 text-base text-secondary focus:border-tertiary focus:ring-2 focus:ring-tertiary/30 focus:outline-none aria-invalid:border-red-600';
    $labelClass = 'mb-1.5 block text-sm font-semibold';
    $invalid = fn (string $field) => $errors?->has($field) ? 'true' : null;
    $old = (array) $old;
@endphp
<form id="{{ $id }}" method="post" action="/forms/contact" data-island="contact-form" novalidate {{ $attributes->class('relative space-y-4') }}>
    <p class="rounded-lg bg-red-50 px-4 py-3 text-sm font-semibold text-red-800 empty:hidden" role="alert" data-form-error>{{ $errors?->first('form') }}</p>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $id }}-name" class="{{ $labelClass }}">Name</label>
            <input id="{{ $id }}-name" name="name" type="text" required autocomplete="name" minlength="2" maxlength="100" value="{{ $old['name'] ?? '' }}" aria-invalid="{{ $invalid('name') }}" aria-describedby="{{ $id }}-name-error" class="{{ $inputClass }}">
            <x-site.form-error field="name" :id="$id.'-name'" :errors="$errors" />
        </div>
        <div>
            <label for="{{ $id }}-phone" class="{{ $labelClass }}">Phone <span class="font-normal text-secondary-light">(optional)</span></label>
            <input id="{{ $id }}-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" placeholder="(555) 555-5555" value="{{ $old['phone'] ?? '' }}" aria-invalid="{{ $invalid('phone') }}" aria-describedby="{{ $id }}-phone-error" class="{{ $inputClass }}">
            <x-site.form-error field="phone" :id="$id.'-phone'" :errors="$errors" />
        </div>
    </div>
    <div>
        <label for="{{ $id }}-email" class="{{ $labelClass }}">Email</label>
        <input id="{{ $id }}-email" name="email" type="email" required autocomplete="email" inputmode="email" maxlength="150" value="{{ $old['email'] ?? '' }}" aria-invalid="{{ $invalid('email') }}" aria-describedby="{{ $id }}-email-error" class="{{ $inputClass }}">
        <x-site.form-error field="email" :id="$id.'-email'" :errors="$errors" />
    </div>
    <div>
        <label for="{{ $id }}-message" class="{{ $labelClass }}">Message</label>
        <textarea id="{{ $id }}-message" name="message" rows="5" required minlength="5" maxlength="5000" aria-invalid="{{ $invalid('message') }}" aria-describedby="{{ $id }}-message-error" class="{{ $inputClass }}">{{ $old['message'] ?? '' }}</textarea>
        <x-site.form-error field="message" :id="$id.'-message'" :errors="$errors" />
    </div>
    <x-site.form-guard form="contact" :old="$old" />
    <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-lg btn-energy px-6 py-3 font-bold disabled:cursor-wait disabled:opacity-70">Send message</button>
</form>
