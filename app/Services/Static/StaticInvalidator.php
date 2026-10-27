<?php

namespace App\Services\Static;

use App\Enums\SystemPage;
use App\Jobs\RebuildPaths;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Author;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MoveType;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Review;
use App\Models\Section;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Which static pages a content change affects (spec §10 dependency map).
 *
 * Model events collect paths during the request; one RebuildPaths job is queued
 * when the request (or console command, or queued job) ends. Anything that shows
 * on every page triggers a full rebuild: menus, settings and theme, move types
 * (quote form), redirects, media edits, and a service or area being added,
 * renamed, switched on/off or moved in the menu (header dropdowns and the
 * business JSON-LD list them).
 */
class StaticInvalidator
{
    /** @var array<string, true> */
    private array $paths = [];

    private bool $full = false;

    /** Fields of a service/area that appear outside its own pages. */
    private const SITEWIDE_FIELDS = ['title', 'city', 'state_code', 'state_name', 'slug', 'is_active', 'show_in_menu', 'show_on_home', 'sort'];

    public function register(): void
    {
        foreach ([Menu::class, MenuItem::class, MoveType::class, Redirect::class] as $model) {
            $this->on($model, fn () => $this->full());
        }

        $this->on(Setting::class, function (Setting $setting) {
            // Switching the cache off must stop serving old files right away, not when the queue gets to it.
            if ($setting->key === 'static.enabled' && ! filter_var($setting->value, FILTER_VALIDATE_BOOL)) {
                app(StaticPublisher::class)->purgeAll();

                return;
            }

            $this->full();
        });

        // Uploads do nothing until used; edits (alt text, crop, replace) and deletes may show anywhere.
        $this->on(Media::class, fn (Media $m) => $this->justCreated($m) ? null : $this->full());

        $this->on(Page::class, fn (Page $p) => $this->page($p));
        $this->on(Section::class, fn (Section $s) => $s->sectionable ? $this->owner($s->sectionable) : null);
        $this->on(SeoMeta::class, fn (SeoMeta $m) => $m->seoable ? $this->owner($m->seoable) : null);
        $this->on(Service::class, fn (Service $s) => $this->service($s));
        $this->on(Area::class, fn (Area $a) => $this->area($a));
        $this->on(AreaServicePage::class, fn (AreaServicePage $p) => $this->areaService($p));
        $this->on(Post::class, fn (Post $p) => $this->post($p));
        $this->on(Category::class, fn (Category $c) => $this->category($c));
        $this->on(Author::class, fn (Author $a) => $a->posts->each(fn (Post $p) => $this->add($p->publicPath())));
        $this->on(Tag::class, fn (Tag $t) => $t->posts->each(fn (Post $p) => $this->add($p->publicPath())));
        $this->on(Review::class, fn (Review $r) => $this->review($r));
        $this->on(Faq::class, fn (Faq $f) => $this->faq($f));

        // Queue workers never "terminate" between jobs: flush after each job too.
        Event::listen(JobProcessed::class, fn () => $this->dispatch());
        app()->terminating(fn () => $this->dispatch());
    }

    /**
     * Queue one rebuild for everything collected so far.
     */
    public function dispatch(): void
    {
        if (! $this->full && $this->paths === []) {
            return;
        }

        $job = new RebuildPaths(array_keys($this->paths), $this->full);
        $this->paths = [];
        $this->full = false;

        // Wait for the transaction (if any) so the job sees the saved data.
        DB::afterCommit(fn () => dispatch($job));
    }

    /** @return array{paths: list<string>, full: bool} what is queued so far (tests, debugging) */
    public function pending(): array
    {
        return ['paths' => array_keys($this->paths), 'full' => $this->full];
    }

    public function full(): void
    {
        $this->full = true;
    }

    public function add(?string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path !== null && $path !== '') {
                $this->paths['/'.ltrim($path, '/')] = true;
            }
        }
    }

    // ---- per model ----------------------------------------------------------------------

    private function page(Page $page): void
    {
        if ($page->system_key === SystemPage::AreaServiceTemplate) {
            // The template drives every city × service page that uses it.
            AreaServicePage::query()->where('use_template', true)->with(['area', 'service'])->get()
                ->each(fn (AreaServicePage $p) => $this->add($p->publicPath()));

            return;
        }

        $this->add($page->publicPath(), $this->oldPath($page));

        // Legal page links and titles are in the footer of every page.
        if (in_array($page->system_key, [SystemPage::Privacy, SystemPage::Terms], true) && $page->wasChanged(['title', 'status', 'slug'])) {
            $this->full();
        }
    }

    private function owner(Model $owner): void
    {
        match (true) {
            $owner instanceof Page => $this->page($owner),
            $owner instanceof Service => $this->add($owner->publicPath()),
            $owner instanceof Area => $this->add($owner->publicPath()),
            $owner instanceof AreaServicePage => $this->add($owner->publicPath()),
            $owner instanceof Post => $this->add($owner->publicPath()),
            $owner instanceof Category => $this->add($owner->publicPath()),
            default => null,
        };
    }

    private function service(Service $service): void
    {
        if ($this->sitewide($service)) {
            $this->full();

            return;
        }

        $this->add($service->publicPath(), '/services', '/');
        $service->areaServicePages()->with('area')->get()->each(fn (AreaServicePage $p) => $this->add($p->publicPath()));
        $service->areas()->get()->each(fn (Area $a) => $this->add($a->publicPath()));
    }

    private function area(Area $area): void
    {
        if ($this->sitewide($area)) {
            $this->full();

            return;
        }

        $this->add($area->publicPath(), '/service-areas', '/');
        $area->areaServicePages()->with('service')->get()->each(fn (AreaServicePage $p) => $this->add($p->publicPath()));
        $area->neighbors()->get()->each(fn (Area $n) => $this->add($n->publicPath()));
        // Cities that list this one as a neighbor link to it too.
        Area::query()->whereHas('neighbors', fn ($q) => $q->whereKey($area->id))->get()->each(fn (Area $n) => $this->add($n->publicPath()));
    }

    private function areaService(AreaServicePage $page): void
    {
        $page->loadMissing(['area', 'service']);

        if (! $page->area || ! $page->service) {
            return;
        }

        // The city page and the service page list the live city × service pages.
        $this->add($page->publicPath(), $page->area->publicPath(), $page->service->publicPath());
        $page->area->areaServicePages()->with('service')->get()->each(fn (AreaServicePage $p) => $this->add($p->publicPath()));
    }

    private function post(Post $post): void
    {
        $this->add($post->publicPath(), $this->oldPath($post), '/blog');
        $this->blogPages();

        $post->loadMissing('category');
        if ($post->category) {
            $this->category($post->category, withPosts: false);
        }

        // Related posts and previous/next links on other posts; service pages list the latest posts.
        Post::query()->published()->latestFirst()->limit(10)->get()->each(fn (Post $p) => $this->add($p->publicPath()));
        Service::query()->active()->get()->each(fn (Service $s) => $this->add($s->publicPath()));
    }

    private function category(Category $category, bool $withPosts = true): void
    {
        $this->add($category->publicPath(), $this->oldPath($category), '/blog');

        foreach (app(StaticPublisher::class)->paths() as $path) {
            if (str_starts_with($path, $category->publicPath().'/page/')) {
                $this->add($path);
            }
        }

        if ($withPosts) {
            $category->posts()->get()->each(fn (Post $p) => $this->add($p->publicPath()));
        }
    }

    private function blogPages(): void
    {
        foreach (app(StaticPublisher::class)->paths() as $path) {
            if (str_starts_with($path, '/blog/page/')) {
                $this->add($path);
            }
        }
    }

    private function review(Review $review): void
    {
        $this->add('/reviews', '/');
        $review->loadMissing(['area', 'service']);
        $this->add($review->area?->publicPath(), $review->service?->publicPath());

        // Review sliders show featured reviews on any page that has one, and city pages fall back to them.
        Section::query()->where('type', 'reviews_slider')->with('sectionable')->get()
            ->each(fn (Section $s) => $s->sectionable ? $this->owner($s->sectionable) : null);
        Area::query()->active()->get()->each(fn (Area $a) => $this->add($a->publicPath()));
    }

    private function faq(Faq $faq): void
    {
        foreach (DB::table('faqables')->where('faq_id', $faq->id)->get() as $row) {
            $model = Relation::getMorphedModel($row->faqable_type);

            if ($model && ($record = $model::query()->find($row->faqable_id))) {
                $this->owner($record);
            }
        }

        Section::query()->where('type', 'faq')->with('sectionable')->get()
            ->filter(fn (Section $s) => in_array($faq->id, array_map('intval', $s->data['faqs'] ?? []), true))
            ->each(fn (Section $s) => $s->sectionable ? $this->owner($s->sectionable) : null);
    }

    // ---- helpers ----------------------------------------------------------------------------

    /** Created, deleted or changed in a way that shows on other pages (menus, schema, grids). */
    private function sitewide(Model $model): bool
    {
        return $this->justCreated($model) || ! $model->exists || method_exists($model, 'trashed') && $model->trashed()
            || $model->wasChanged(self::SITEWIDE_FIELDS);
    }

    /**
     * This save was the insert. (wasRecentlyCreated stays true for later saves of
     * the same instance in one request; an insert has no "changes".)
     */
    private function justCreated(Model $model): bool
    {
        return $model->wasRecentlyCreated && $model->getChanges() === [];
    }

    /** The URL before a slug change: rebuilding it removes the stale file. */
    private function oldPath(Model $model): ?string
    {
        if (! $model->wasChanged('slug') || ! method_exists($model, 'pathForSlug')) {
            return null;
        }

        return $model->pathForSlug((string) $model->getOriginal('slug'));
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function on(string $model, \Closure $handler): void
    {
        $run = function (Model $record) use ($handler) {
            if (config('static.auto')) {
                $handler($record);
            }
        };

        $model::saved($run);
        $model::deleted($run);

        if (method_exists($model, 'bootSoftDeletes')) {
            $model::restored($run);
        }
    }
}
