@props(['compact' => false])
{{--
    Social profile icons from Settings > General, in config('social.networks') order.
    Networks without a URL are left out; with none set, nothing renders.
    compact: the small version for the top contact bar.
--}}
@php
    use App\Support\Settings;

    $urls = (array) Settings::get('general.social', []);
    $networks = collect(config('social.networks'))->filter(fn ($network, $key) => filled($urls[$key] ?? null));
    $company = Settings::get('general.company_name');
@endphp
@if ($networks->isNotEmpty())
    <ul {{ $attributes->class(['flex flex-wrap items-center', 'gap-1' => $compact, 'gap-2.5' => ! $compact]) }} aria-label="Social media">
        @foreach ($networks as $key => $network)
            <li>
                <a
                    href="{{ $urls[$key] }}"
                    target="_blank"
                    rel="noopener"
                    aria-label="{{ $company }} on {{ $network['label'] }}"
                    style="--social-brand: {{ $network['background'] }}"
                    @class([
                        'flex items-center justify-center rounded-full text-primary transition duration-200 hover:text-white hover:[background:var(--social-brand)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tertiary',
                        'size-7' => $compact,
                        'size-11 bg-white/10 ring-1 ring-white/15 hover:shadow-lg hover:ring-transparent motion-safe:hover:-translate-y-0.5' => ! $compact,
                    ])
                >
                    <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="currentColor" @class(['size-3.5' => $compact, 'size-5' => ! $compact])><path d="{{ $network['path'] }}"/></svg>
                </a>
            </li>
        @endforeach
    </ul>
@endif
