<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

// Laravel's defer(), not Swoole's global defer() (the Sail image ships the Swoole extension).
use function Illuminate\Support\defer;

/**
 * Before routing: admin-managed redirects (301/302) and the "no trailing slash"
 * URL rule. The redirect map is cached; hits are counted after the response.
 */
class HandleRedirects
{
    public const CACHE_KEY = 'redirects.map';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = '/'.ltrim($request->getPathInfo(), '/');

        if ($path !== '/' && str_ends_with($path, '/')) {
            return redirect()->to(rtrim($path, '/').$this->query($request), 301);
        }

        if (str_starts_with($path, '/admin') || str_starts_with($path, '/build/') || str_starts_with($path, '/storage/')) {
            return $next($request);
        }

        $target = static::map()[strtolower(rawurldecode($path))] ?? null;

        if ($target === null) {
            return $next($request);
        }

        defer(fn () => Redirect::query()->whereKey($target['id'])->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]));

        $to = $target['to'];
        // Keep campaign tags and other query parameters on internal redirects.
        if (str_starts_with($to, '/') && ! str_contains($to, '?')) {
            $to .= $this->query($request);
        }

        return redirect()->to($to, $target['status'])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * from_path (lower-case) => {id, to, status}
     *
     * @return array<string, array{id: int, to: string, status: int}>
     */
    public static function map(): array
    {
        return Cache::rememberForever(static::CACHE_KEY, fn () => Redirect::query()
            ->where('is_active', true)
            ->get(['id', 'from_path', 'to_path', 'status_code'])
            ->mapWithKeys(fn (Redirect $r) => [strtolower($r->from_path) => ['id' => $r->id, 'to' => $r->to_path, 'status' => $r->status_code === 302 ? 302 : 301]])
            ->all());
    }

    private function query(Request $request): string
    {
        $qs = $request->getQueryString();

        return $qs ? '?'.$qs : '';
    }
}
