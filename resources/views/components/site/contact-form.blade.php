@props(['id' => 'contact-form'])
{{-- Contact form. Plain POST; T12 adds the submit handling and emails. --}}
@php
    $inputClass = 'block w-full rounded-lg border border-border bg-white px-4 py-3 text-base text-secondary focus:border-tertiary focus:ring-2 focus:ring-tertiary/30 focus:outline-none';
    $labelClass = 'mb-1.5 block text-sm font-semibold';
@endphp
<form id="{{ $id }}" method="post" action="/forms/contact" data-island="contact-form" novalidate {{ $attributes->class('space-y-4') }}>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $id }}-name" class="{{ $labelClass }}">Name</label>
            <input id="{{ $id }}-name" name="name" type="text" required autocomplete="name" class="{{ $inputClass }}">
        </div>
        <div>
            <label for="{{ $id }}-phone" class="{{ $labelClass }}">Phone</label>
            <input id="{{ $id }}-phone" name="phone" type="tel" autocomplete="tel" class="{{ $inputClass }}">
        </div>
    </div>
    <div>
        <label for="{{ $id }}-email" class="{{ $labelClass }}">Email</label>
        <input id="{{ $id }}-email" name="email" type="email" required autocomplete="email" class="{{ $inputClass }}">
    </div>
    <div>
        <label for="{{ $id }}-message" class="{{ $labelClass }}">Message</label>
        <textarea id="{{ $id }}-message" name="message" rows="5" required class="{{ $inputClass }}"></textarea>
    </div>
    <div class="absolute -left-[9999px]" aria-hidden="true">
        <label for="{{ $id }}-website">Website</label>
        <input id="{{ $id }}-website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>
    <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-tertiary px-6 py-3 font-bold text-white hover:bg-tertiary-hover">Send message</button>
</form>
