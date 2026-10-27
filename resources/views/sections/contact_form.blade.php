{{-- Contact form with the company's contact details. --}}
@php($hours = \App\Support\Contact::hours())
<x-site.section background="light" :anchor="$anchor">
    <div class="grid gap-12 lg:grid-cols-[1.3fr_1fr]">
        <div>
            <x-site.heading :heading="$s['heading'] ?? null" :intro="$s['intro'] ?? null" :tag="$headingTag" align="left" />
            <x-site.contact-form id="contact-{{ $section->id }}" />
        </div>
        @if ($s['show_details'] ?? true)
            <aside class="space-y-6 rounded-2xl bg-muted p-6 sm:p-8" aria-label="Contact details">
                <div class="flex gap-3"><x-site.icon name="phone" class="size-6 text-tertiary" /><div><p class="font-bold">Phone</p><a href="{{ \App\Support\Contact::telHref() }}" class="text-lg hover:text-tertiary" data-call-location="contact">{{ \App\Support\Contact::phone() }}</a></div></div>
                <div class="flex gap-3"><x-site.icon name="mail" class="size-6 text-tertiary" /><div><p class="font-bold">Email</p><a href="mailto:{{ \App\Support\Contact::email() }}" class="hover:text-tertiary">{{ \App\Support\Contact::email() }}</a></div></div>
                <div class="flex gap-3"><x-site.icon name="map-pin" class="size-6 text-tertiary" /><div><p class="font-bold">Office</p><address class="not-italic">{{ \App\Support\Contact::address() }}</address></div></div>
                @if ($hours)
                    <div class="flex gap-3"><x-site.icon name="clock" class="size-6 text-tertiary" /><div><p class="font-bold">Hours</p>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4">@foreach ($hours as $row)<dt>{{ $row['days'] }}</dt><dd>{{ $row['hours'] }}</dd>@endforeach</dl>
                    </div></div>
                @endif
            </aside>
        @endif
    </div>
</x-site.section>
