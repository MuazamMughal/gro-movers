{{-- Compact call to action inside an article (after the 2nd section of a blog post). --}}
<aside class="not-prose my-12 rounded-2xl bg-glow-navy p-6 text-primary sm:p-8" aria-label="Get a free quote" data-theme="dark">
    <p class="text-2xl font-extrabold tracking-tight">Planning a move?</p>
    <p class="mt-2 text-primary/85">Get a free, no-obligation quote from {{ \App\Support\Settings::get('general.company_name') }} in minutes.</p>
    <div class="mt-5 flex flex-wrap gap-3">
        <x-site.button :href="\App\Support\QuoteLink::href()" label="Get a Free Quote" />
        <x-site.call-button variant="outline-light" location="blog_inline" />
    </div>
</aside>
