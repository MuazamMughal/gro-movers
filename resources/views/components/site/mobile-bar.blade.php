{{--
    Sticky Call Now / Get a Free Quote bar on phones (below 768px), 50/50 with 56px tap targets.
    The layout adds matching bottom padding to <body>, so it never covers content.
--}}
@php($phone = \App\Support\Contact::phone())
<nav class="fixed inset-x-0 bottom-0 z-40 grid h-16 grid-cols-2 border-t border-border bg-primary pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgb(0_0_0/0.08)] md:hidden" aria-label="Call or get a quote">
    @if ($phone)
        <a href="{{ \App\Support\Contact::telHref() }}" class="flex items-center justify-center gap-2 font-bold text-secondary active:bg-muted" data-call-location="mobile_bar">
            <x-site.icon name="phone" class="size-5 text-tertiary" /> Call Now
        </a>
    @endif
    <a href="/get-a-quote" @class(['flex items-center justify-center gap-2 bg-tertiary-strong font-bold text-white active:bg-tertiary-hover', 'col-span-2' => ! $phone])>
        Get a Free Quote
    </a>
</nav>
