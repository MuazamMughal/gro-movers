<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\SeoFileController;
use App\Models\Redirect;
use App\Seo\SeoAudit;
use App\Seo\SiteIndex;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SEO Tools: the report (needs attention / duplicates / hidden from Google),
 * redirects (CRUD, hits, CSV import), the robots.txt editor and the sitemap.
 */
class SeoToolsController extends Controller
{
    public function report(): Response
    {
        return Inertia::render('seo/Report', SeoAudit::run());
    }

    /** Live duplicate check for the SEO panel on content forms. */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:500'],
            'h1' => ['nullable', 'string', 'max:300'],
        ]);

        return response()->json([
            'clashes' => SeoAudit::clashes($data['path'] ?? null, $data['title'] ?? null, $data['description'] ?? null, $data['h1'] ?? null),
        ]);
    }

    // ---- Redirects ---------------------------------------------------------------

    public function redirects(Request $request): Response
    {
        $q = trim((string) $request->query('q'));

        return Inertia::render('seo/Redirects', [
            'redirects' => Redirect::query()
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('from_path', 'like', "%{$q}%")->orWhere('to_path', 'like', "%{$q}%")))
                ->orderByDesc('updated_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (Redirect $r) => [
                    'id' => $r->id,
                    'from_path' => $r->from_path,
                    'to_path' => $r->to_path,
                    'status_code' => $r->status_code,
                    'hits' => $r->hits,
                    'last_hit_at' => $r->last_hit_at?->diffForHumans(),
                    'is_active' => $r->is_active,
                ]),
            'filters' => ['q' => $q],
        ]);
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        $data = $this->validatedRedirect($request);

        Redirect::query()->create($data + ['hits' => 0]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Redirect from {$data['from_path']} added."]);

        return back();
    }

    public function updateRedirect(Request $request, Redirect $redirect): RedirectResponse
    {
        $redirect->update($this->validatedRedirect($request, $redirect));

        return back();
    }

    public function destroyRedirect(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return back();
    }

    /**
     * CSV with "from,to" or "from,to,status" rows (a header row is skipped).
     * Existing redirects for the same old URL are updated.
     */
    public function importRedirects(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        [$imported, $skipped] = [0, []];
        $line = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $from = static::normalizePath($row[0] ?? '');
            $to = trim((string) ($row[1] ?? ''));
            $status = (int) ($row[2] ?? 301);

            if ($line === 1 && ! str_starts_with(trim((string) ($row[0] ?? '')), '/') && ! preg_match('#^https?://#i', trim((string) ($row[0] ?? '')))) {
                continue; // header row
            }

            if ($from === null || ! static::validTarget($to) || $from === static::normalizePath($to) || ! in_array($status, [301, 302], true)) {
                $skipped[] = $line;

                continue;
            }

            Redirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => $status, 'is_active' => true]);
            $imported++;
        }

        fclose($handle);

        $message = "{$imported} ".str('redirect')->plural($imported).' imported.';
        if ($skipped) {
            $message .= ' Skipped lines: '.implode(', ', array_slice($skipped, 0, 20)).(count($skipped) > 20 ? '…' : '').'.';
        }

        Inertia::flash('toast', ['type' => $skipped ? 'warning' : 'success', 'message' => $message]);

        return back();
    }

    // ---- robots.txt and sitemap -------------------------------------------------------

    public function robots(): Response
    {
        return Inertia::render('seo/Robots', [
            'robots' => (string) Settings::get('seo.robots_txt'),
            'preview' => SeoFileController::robotsTxt(),
            'sitemapUrl' => url('/sitemap.xml'),
            'sitemapCount' => SiteIndex::indexable()->count(),
            'production' => app()->isProduction(),
        ]);
    }

    public function updateRobots(Request $request): RedirectResponse
    {
        $robots = (string) $request->validate(['robots' => ['required', 'string', 'max:5000']])['robots'];

        // The one line that would take the whole site out of Google.
        if (app()->isProduction() && preg_match('#^\s*Disallow:\s*/\s*$#mi', $robots)) {
            throw ValidationException::withMessages(['robots' => '"Disallow: /" blocks the whole website from Google. It cannot be saved on the live site.']);
        }

        Settings::set('seo.robots_txt', str_replace("\r\n", "\n", trim($robots)));
        Settings::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'robots.txt saved.']);

        return back();
    }

    public function regenerateSitemap(): RedirectResponse
    {
        SeoFileController::flushSitemap();
        $count = substr_count(SeoFileController::sitemapXml(), '<url>');

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sitemap regenerated with {$count} URLs."]);

        return back();
    }

    // ---- helpers ------------------------------------------------------------------------

    /**
     * @return array{from_path: string, to_path: string, status_code: int, is_active: bool}
     */
    private function validatedRedirect(Request $request, ?Redirect $redirect = null): array
    {
        $request->merge(['from_path' => static::normalizePath((string) $request->input('from_path')) ?? $request->input('from_path')]);

        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:255', 'regex:~^/[^\s?#]*$~', 'not_regex:#^/admin#', Rule::unique('redirects', 'from_path')->ignore($redirect)],
            'to_path' => ['required', 'string', 'max:2048', fn ($a, $v, $fail) => static::validTarget((string) $v) ? null : $fail('Use a path starting with / or a full https:// link.')],
            'status_code' => ['required', Rule::in([301, 302])],
            'is_active' => ['boolean'],
        ], [
            'from_path.regex' => 'Use the old path only, starting with / (e.g. /old-page).',
            'from_path.not_regex' => 'Admin pages cannot be redirected.',
            'from_path.unique' => 'There is already a redirect from this URL.',
        ], ['from_path' => 'old URL', 'to_path' => 'new URL']);

        if (static::normalizePath($data['to_path']) === $data['from_path']) {
            throw ValidationException::withMessages(['to_path' => 'The new URL must be different from the old one.']);
        }

        return [...$data, 'status_code' => (int) $data['status_code'], 'is_active' => (bool) ($data['is_active'] ?? true)];
    }

    /** "https://site.com/Old-Page/?x" or "old-page" → "/old-page" (no query, no trailing slash). */
    public static function normalizePath(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $path = parse_url(preg_match('#^https?://#i', $value) ? $value : '/'.ltrim($value, '/'), PHP_URL_PATH) ?: '/';
        $path = '/'.trim(rawurldecode($path), '/');

        return $path === '/' ? null : strtolower($path);
    }

    private static function validTarget(string $to): bool
    {
        return (bool) preg_match('#^(/[^\s]*|https?://[^\s]+)$#i', trim($to));
    }
}
