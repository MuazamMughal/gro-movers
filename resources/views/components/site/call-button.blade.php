@props(['variant' => 'outline', 'size' => 'md', 'location' => 'cta', 'compact' => false])
{{-- "Call Now" button with the phone number visible (compact: "Call Now" only, the number stays in the tel: link and aria-label). data-call-location feeds the GA4 click_to_call event (T13). --}}
@php($phone = \App\Support\Contact::phone())
@if ($phone)
    <x-site.button :href="\App\Support\Contact::telHref()" :label="$compact ? 'Call Now' : 'Call '.$phone" :aria-label="$compact ? 'Call '.$phone : null" :variant="$variant" :size="$size" data-call-location="{{ $location }}" {{ $attributes }}>
        <x-site.icon name="phone" class="size-5" />
    </x-site.button>
@endif
