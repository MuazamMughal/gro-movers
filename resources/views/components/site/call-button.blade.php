@props(['variant' => 'outline', 'size' => 'md', 'location' => 'cta'])
{{-- "Call Now" button with the phone number visible. data-call-location feeds the GA4 click_to_call event (T13). --}}
@php($phone = \App\Support\Contact::phone())
@if ($phone)
    <x-site.button :href="\App\Support\Contact::telHref()" :label="'Call '.$phone" :variant="$variant" :size="$size" data-call-location="{{ $location }}" {{ $attributes }}>
        <x-site.icon name="phone" class="size-5" />
    </x-site.button>
@endif
