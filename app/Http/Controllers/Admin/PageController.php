<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Sections\SectionAdmin;
use App\Support\FaqAttach;
use App\Support\SeoForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin pages: system pages (home, about, contact…) and custom pages.
 * Content is the section builder; every page has the shared SEO tab.
 */
class PageController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->value();
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;

        $pages = Page::query()
            ->with('seo')
            ->withCount('sections')
            ->where(fn (Builder $q) => $q->whereNull('system_key')->orWhere('system_key', '!=', SystemPage::AreaServiceTemplate))
            ->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            // System pages first, in their natural order, then custom pages A–Z.
            ->orderByRaw('system_key is null')
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'path' => $page->publicPath(),
                'status' => $page->status->value,
                'is_system' => $page->isSystem(),
                'system_label' => $page->system_key ? Str::headline($page->system_key->value) : null,
                'noindex' => ! $page->seoIndexable(),
                'seo_missing' => $page->seoUsesFallbacks(),
                'sections_count' => $page->sections_count,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('pages/Index', [
            'pages' => $pages,
            'filters' => ['q' => $search ?: null, 'status' => $status],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('pages/Form', [
            'page' => null,
            'faqIds' => [],
            'seo' => SeoForm::props(null, null),
            ...SectionAdmin::props(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $page = DB::transaction(function () use ($request, $data) {
            $page = Page::query()->create($this->attributes($data, null));
            SectionAdmin::save($page, $request);
            SeoForm::save($page, $data['seo'] ?? []);
            FaqAttach::sync($page, $data['faq_ids'] ?? []);

            return $page;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $page->isPublished() ? 'Page published.' : 'Draft saved.']);

        return to_route('admin.pages.edit', $page);
    }

    public function edit(Page $page): Response
    {
        abort_if($page->system_key === SystemPage::AreaServiceTemplate, 404);

        $page->load('seo');

        return Inertia::render('pages/Form', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'is_system' => $page->isSystem(),
                'system_label' => $page->system_key ? Str::headline($page->system_key->value) : null,
                'slug_locked' => $page->system_key?->slugLocked() ?? false,
                'path' => $page->publicPath(),
                'published_at' => $page->published_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
            'faqIds' => FaqAttach::ids($page),
            'seo' => SeoForm::props($page, $page->publicPath()),
            ...SectionAdmin::props($page),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        abort_if($page->system_key === SystemPage::AreaServiceTemplate, 404);

        $data = $this->validated($request, $page);
        $wasPublished = $page->isPublished();

        DB::transaction(function () use ($request, $page, $data) {
            $page->update($this->attributes($data, $page));
            SectionAdmin::save($page, $request);
            SeoForm::save($page, $data['seo'] ?? []);
            FaqAttach::sync($page, $data['faq_ids'] ?? []);
        });

        $message = match (true) {
            $page->isPublished() && ! $wasPublished => 'Page published.',
            ! $page->isPublished() && $wasPublished => 'Page unpublished. It is now a draft.',
            $page->isPublished() => 'Page updated.',
            default => 'Draft saved.',
        };
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.pages.edit', $page);
    }

    public function destroy(Page $page): RedirectResponse
    {
        if ($page->isSystem()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'System pages cannot be deleted.']);

            return back();
        }

        $page->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "“{$page->title}” moved to trash."]);

        return to_route('admin.pages.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Page $page = null): array
    {
        $slugLocked = $page?->system_key?->slugLocked() ?? false;

        $rules = [
            'title' => ['required', 'string', 'max:150'],
            'slug' => $slugLocked ? ['prohibited'] : [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Page::RESERVED_SLUGS),
                Rule::unique('pages', 'slug')->ignore($page),
            ],
            'status' => ['required', Rule::enum(PageStatus::class)],
            ...FaqAttach::rules(),
            ...SeoForm::rules(),
            ...SectionAdmin::rules($request),
        ];

        return $request->validate(
            $rules,
            [
                'slug.regex' => 'Use lowercase letters, numbers and single hyphens only (e.g. moving-checklist).',
                'slug.not_in' => 'This URL is reserved by the website. Please choose another one.',
                'slug.prohibited' => 'The URL of this page cannot be changed.',
            ],
            [...SeoForm::attributes(), ...SectionAdmin::attributes($request), 'slug' => 'URL'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?Page $page): array
    {
        $status = PageStatus::from($data['status']);

        return array_filter([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'status' => $status,
            // First publish date is kept when a page is later re-saved.
            'published_at' => $status === PageStatus::Published ? ($page?->published_at ?? now()) : $page?->published_at,
        ], fn ($v) => $v !== null) + ($page === null ? ['template' => 'default'] : []);
    }
}
