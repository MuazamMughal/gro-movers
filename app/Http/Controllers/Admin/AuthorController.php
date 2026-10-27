<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post authors, shown in the post byline and author box (E-E-A-T).
 */
class AuthorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('authors/Index', [
            'authors' => Author::query()->orderBy('name')->with('photo.variants')->withCount('posts')->get()
                ->map(fn (Author $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'job_title' => $a->job_title,
                    'posts_count' => $a->posts_count,
                    'thumb' => $a->photo?->url(480),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('authors/Form', ['author' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $author = Author::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Author “{$author->name}” created."]);

        return to_route('admin.authors.edit', $author);
    }

    public function edit(Author $author): Response
    {
        return Inertia::render('authors/Form', [
            'author' => $author->only(['id', 'name', 'slug', 'job_title', 'bio', 'photo_media_id']) + ['posts_count' => $author->posts()->count()],
        ]);
    }

    public function update(Request $request, Author $author): RedirectResponse
    {
        $author->update($this->validated($request, $author));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Author saved.']);

        return to_route('admin.authors.edit', $author);
    }

    public function destroy(Author $author): RedirectResponse
    {
        $author->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Author “{$author->name}” deleted. Their posts now show no author."]);

        return to_route('admin.authors.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Author $author = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('authors', 'slug')->ignore($author)],
            'job_title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'photo_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only.',
        ], [
            'slug' => 'URL name',
            'photo_media_id' => 'photo',
        ]);
    }
}
