{{--
    Standalone error page (500, 503, 419): no database, no Vite, inline styles,
    so it still renders when the app itself is what failed.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; box-sizing: border-box;
               font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #f1f5fa; color: #0a2e5c; }
        main { max-width: 34rem; text-align: center; }
        p.code { margin: 0; font-size: .8rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: #f66419; }
        h1 { margin: .75rem 0 0; font-size: 2rem; line-height: 1.2; }
        p { font-size: 1.1rem; line-height: 1.6; color: #45607f; }
        a { display: inline-block; margin: .5rem .25rem 0; padding: .8rem 1.4rem; border-radius: .6rem; font-weight: 700; text-decoration: none; }
        a.primary { background: #f66419; color: #fff; }
        a.secondary { border: 2px solid #0a2e5c; color: #0a2e5c; }
    </style>
</head>
<body>
    <main>
        <p class="code">Error {{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a class="primary" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
        <a class="secondary" href="/">Home page</a>
    </main>
</body>
</html>
