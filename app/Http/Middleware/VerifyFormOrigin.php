<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public form posts skip CSRF (the pages are static HTML), so they must come
 * from this site: the Origin header, or else the Referer, has to be our host.
 */
class VerifyFormOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        $host = $source ? parse_url($source, PHP_URL_HOST) : null;

        abort_unless($host && in_array(strtolower($host), $this->allowedHosts($request), true), 403, 'Forms can only be sent from this website.');

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(Request $request): array
    {
        $hosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            Settings::get('seo.canonical_host'),
            $request->getHost(),
        ]);

        // Accept both example.com and www.example.com.
        $bare = array_map(fn (string $h) => preg_replace('/^www\./', '', strtolower($h)), $hosts);

        return array_values(array_unique([...$bare, ...array_map(fn (string $h) => 'www.'.$h, $bare)]));
    }
}
