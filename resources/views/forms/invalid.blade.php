@extends('layouts.site')

{{--
    Shown after a no-JS form submit that failed validation: the static page cannot show
    errors, so the form comes back here filled in, with the messages. Never indexed.
--}}
@section('content')
    <x-site.section background="muted" width="narrow">
        <h1 class="text-3xl font-extrabold tracking-tight md:text-4xl">Please check your details</h1>
        <p class="mt-3 mb-8 text-lg text-secondary-light">A few fields need a fix before we can send your {{ $form === 'quote' ? 'quote request' : 'message' }}.</p>
        @if ($form === 'quote')
            <x-site.quote-form id="quote-retry" :old="$old" :errors="$errors" />
        @else
            <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-black/5 sm:p-8">
                <x-site.contact-form id="contact-retry" :old="$old" :errors="$errors" />
            </div>
        @endif
    </x-site.section>
@endsection
