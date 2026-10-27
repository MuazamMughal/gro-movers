<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\SeoForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Blog categories (Moving Tips, Packing Guides…), each with a page at /blog/category/{slug}.
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('blog-categories/Index', [
            'categories' => Category::query()->ordered()->with('seo')->withCount([
                'posts',
                'posts as live_count' => fn ($q) => $q->published(),
            ])->get()->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'path' => $c->publicPath(),
                'posts_count' => $c->posts_count,
                'live_count' => $c->live_count,
                'noindex' => ! $c->seoIndexable(),
                'seo_missing' => $c->seoUsesFallbacks(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('blog-categories/Form', $this->formProps(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $category = DB::transaction(function () use ($data) {
            $category = Category::query()->create([
                ...$this->attributes($data),
                'sort' => (int) Category::query()->max('sort') + 1,
            ]);
            SeoForm::save($category, $data['seo'] ?? []);

            return $category;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category “{$category->name}” created."]);

        return to_route('admin.blog-categories.edit', $category);
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('blog-categories/Form', $this->formProps($category->load('seo')));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);

        DB::transaction(function () use ($category, $data) {
            $category->update($this->attributes($data));
            SeoForm::save($category, $data['seo'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category saved.']);

        return to_route('admin.blog-categories.edit', $category);
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Every post needs a category, so a category with posts cannot go.
        if ($count = $category->posts()->withTrashed()->count()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "“{$category->name}” still has {$count} ".str('post')->plural($count).'. Move them to another category first.']);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category “{$category->name}” deleted."]);

        return to_route('admin.blog-categories.index');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:categories,id']])['ids'];

        DB::transaction(fn () => collect($ids)->each(fn (int $id, int $sort) => Category::query()->whereKey($id)->update(['sort' => $sort])));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Category $category): array
    {
        return [
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'path' => $category->publicPath(),
                'posts_count' => $category->posts()->count(),
            ] : null,
            'seo' => SeoForm::props($category, $category?->publicPath()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:500'],
            ...SeoForm::rules(),
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only (e.g. moving-tips).',
        ], [
            ...SeoForm::attributes(),
            'slug' => 'URL',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ];
    }
}
