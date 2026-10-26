<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ \App\Support\Settings::get('general.company_name', config('app.name')) }}</title>
    @fonts('inter')
    @vite('resources/css/site.css')
    <x-site.theme-vars />
</head>
<body class="font-sans">
    <main class="grid min-h-screen place-items-center px-4">
        <div class="max-w-xl text-center">
            <x-picture :media="\App\Support\Settings::get('branding.logo_light_id')" sizes="320px" eager class="mx-auto mb-8 block w-80" img-class="h-auto w-full" />
            <p class="text-sm font-semibold tracking-wide text-tertiary uppercase">Coming soon</p>
            <h1 class="mt-3 text-4xl font-extrabold tracking-tight">
                {{ \App\Support\Settings::get('general.company_name', config('app.name')) }}
            </h1>
            <p class="mt-4 text-secondary-light">Our new website is under construction.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="tel:{{ preg_replace('/\D+/', '', (string) \App\Support\Settings::get('general.phone')) }}"
                   class="rounded-lg border-2 border-secondary px-5 py-3 font-semibold hover:bg-muted">
                    Call {{ \App\Support\Settings::get('general.phone') }}
                </a>
                <span class="rounded-lg bg-tertiary px-5 py-3 font-semibold text-white">Get a Free Quote</span>
            </div>
        </div>
    </main>
</body>
</html>
