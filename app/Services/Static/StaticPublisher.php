<?php

namespace App\Services\Static;

use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\SeoFileController;
use App\Jobs\BuildStaticPages;
use App\Models\Category;
use App\Models\Post;
use App\Models\StaticBuild;
use App\Seo\SiteIndex;
use App\Seo\SiteUrl;
use App\Support\Settings;
use App\View\Components\Picture;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Once;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pre-renders public pages to public/static/{path}/index.html (home is
 * public/static/index.html). Apache serves those files without starting PHP
 * (public/.htaccess); a missing file simply falls through to Laravel.
 *
 *   $publisher->build('/services/local-moving');   // one page, now
 *   $publisher->buildAll();                        // queued, chunked, with orphan cleanup
 *   $publisher->purge('/old-page');
 */
class StaticPublisher
{
    public const BATCH_NAME = 'static-site';

    public function enabled(): bool
    {
        return (bool) Settings::get('static.enabled', true);
    }

    public function root(): string
    {
        return rtrim((string) config('static.path'), '/');
    }

    /** public/static/about-us/index.html (or public/static/index.html for "/"). */
    public function fileFor(string $path): string
    {
        $path = trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');

        return $this->root().($path === '' ? '' : '/'.$path).'/index.html';
    }

    /**
     * Every URL that gets a static file: all public pages (the sitemap's source,
     * noindex pages included) plus blog and category pagination.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        Once::flush();

        $paths = SiteIndex::all()->map(fn (SiteUrl $u) => $u->path);

        $pages = function (int $posts, string $base) {
            $last = (int) ceil($posts / BlogController::PER_PAGE);

            return $last >= 2 ? collect(range(2, $last))->map(fn ($n) => "{$base}/page/{$n}") : collect();
        };

        // /blog shows the featured post on page 1 and leaves it out of the grid.
        $live = Post::query()->published()->count();
        $featured = Post::query()->published()->where('is_featured', true)->exists() ? 1 : 0;
        $paths = $paths->concat($pages($live - $featured, '/blog'));

        foreach (Category::query()->withCount(['posts' => fn ($q) => $q->published()])->get() as $category) {
            if ($category->posts_count > 0) {
                $paths = $paths->concat($pages($category->posts_count, $category->publicPath()));
            }
        }

        return $paths->unique()->values()->all();
    }

    /**
     * Render one path and write (status 200) or delete (anything else) its file.
     *
     * @return string ok, removed or failed
     */
    public function build(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        if (! $this->enabled()) {
            return 'removed';
        }

        try {
            $response = $this->render($path);
            $status = $response->getStatusCode();
            $type = (string) $response->headers->get('Content-Type');

            if ($status === 200 && str_contains($type, 'text/html')) {
                $html = (string) $response->getContent();
                $html = config('static.minify') ? HtmlMinifier::minify($html) : $html;
                // Inline the CSS this page uses; the full stylesheet then loads without blocking.
                $html = config('static.critical_css') ? CriticalCss::apply($html) : $html;
                $this->write($this->fileFor($path), $html);
                $result = 'ok';
                $error = null;
            } else {
                // Gone, redirected or not HTML: never serve a stale copy.
                $this->purge($path);
                $result = 'removed';
                $error = "HTTP {$status}";
            }
        } catch (Throwable $e) {
            report($e);
            $result = 'failed';
            $error = $e->getMessage();
        }

        StaticBuild::query()->create(['path' => $path, 'status' => $result, 'error' => $error ? mb_substr($error, 0, 1000) : null, 'built_at' => now()]);

        return $result;
    }

    /**
     * Build these paths now (used by the queued jobs).
     *
     * @param  iterable<string>  $paths
     * @return array{ok: int, removed: int, failed: int}
     */
    public function buildMany(iterable $paths): array
    {
        $counts = ['ok' => 0, 'removed' => 0, 'failed' => 0];

        foreach ($paths as $path) {
            $counts[$this->build($path)]++;
        }

        return $counts;
    }

    /**
     * Rebuild the whole site in queued chunks; afterwards delete files of pages
     * that no longer exist and refresh the sitemap.
     *
     * @return int number of pages queued
     */
    public function buildAll(): int
    {
        if (! $this->enabled()) {
            $this->purgeAll();

            return 0;
        }

        $paths = $this->paths();
        $jobs = collect($paths)->chunk((int) config('static.chunk', 25))->map(fn ($chunk) => new BuildStaticPages($chunk->values()->all()))->all();

        Bus::batch($jobs)
            ->name(static::BATCH_NAME)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($paths) {
                $publisher = app(StaticPublisher::class);
                $publisher->purgeOrphans($paths);
                SeoFileController::flushSitemap();
            })
            ->dispatch();

        return count($paths);
    }

    /** The full rebuild that is running now, if any (for the dashboard). */
    public function runningBatch(): ?Batch
    {
        $id = DB::table('job_batches')->where('name', static::BATCH_NAME)->whereNull('finished_at')->whereNull('cancelled_at')->latest('created_at')->value('id');

        return $id ? Bus::findBatch($id) : null;
    }

    public function purge(string $path): void
    {
        $file = $this->fileFor($path);

        if (is_file($file)) {
            @unlink($file);
            $this->removeEmptyDirectories(dirname($file));
        }
    }

    /** Delete every static file (cache switched off): Laravel then renders everything live. */
    public function purgeAll(): void
    {
        if (is_dir($this->root())) {
            foreach (File::allFiles($this->root()) as $file) {
                if ($file->getFilename() === 'index.html') {
                    @unlink($file->getPathname());
                }
            }

            foreach (array_reverse(File::directories($this->root())) as $dir) {
                $this->removeEmptyDirectories($dir);
            }
        }
    }

    /**
     * Remove files of pages that are not in $keep (deleted pages, renamed URLs).
     *
     * @param  list<string>  $keep
     * @return int files removed
     */
    public function purgeOrphans(array $keep): int
    {
        if (! is_dir($this->root())) {
            return 0;
        }

        $wanted = array_flip(array_map(fn ($p) => $this->fileFor($p), $keep));
        $removed = 0;

        foreach (File::allFiles($this->root()) as $file) {
            if ($file->getFilename() === 'index.html' && ! isset($wanted[$file->getPathname()])) {
                @unlink($file->getPathname());
                $this->removeEmptyDirectories($file->getPath());
                $removed++;
            }
        }

        return $removed;
    }

    /** Number of pages currently in the static cache. */
    public function fileCount(): int
    {
        return is_dir($this->root()) ? collect(File::allFiles($this->root()))->filter(fn ($f) => $f->getFilename() === 'index.html')->count() : 0;
    }

    /**
     * Render through the HTTP kernel exactly like a visitor's request.
     */
    private function render(string $path): Response
    {
        // Per-request caches would otherwise leak from the previous page (or job).
        Once::flush();
        Picture::flushCache();

        $request = Request::create(rtrim((string) config('app.url'), '/').$path, 'GET', server: ['HTTP_X_STATIC_BUILD' => '1', 'HTTP_ACCEPT' => 'text/html']);
        $kernel = app(Kernel::class);
        $previous = app()->bound('request') ? app('request') : null;

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        } finally {
            if ($previous) {
                app()->instance('request', $previous);
            }
        }

        return $response;
    }

    private function write(string $file, string $html): void
    {
        File::ensureDirectoryExists(dirname($file));

        // Atomic: visitors never see a half-written page.
        $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($tmp, $html, LOCK_EX);
        @chmod($tmp, 0644);
        rename($tmp, $file);
    }

    private function removeEmptyDirectories(string $dir): void
    {
        $root = $this->root();

        while (str_starts_with($dir, $root.'/') && is_dir($dir) && count(scandir($dir)) === 2) {
            @rmdir($dir);
            $dir = dirname($dir);
        }
    }
}
