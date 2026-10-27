<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post tags. Usually created from the post editor; renamed or removed here.
 */
class TagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('tags/Index', [
            'tags' => Tag::query()->orderBy('name')->withCount('posts')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $this->validatedName($request);

        Tag::query()->create(['name' => $name, 'slug' => Str::slug($name)]);

        return back();
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $name = $this->validatedName($request, $tag);

        $tag->update(['name' => $name, 'slug' => Str::slug($name)]);

        return back();
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tag “{$tag->name}” removed from all posts."]);

        return back();
    }

    private function validatedName(Request $request, ?Tag $tag = null): string
    {
        $request->validate([
            'name' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail) use ($tag) {
                $slug = Str::slug((string) $value);

                if ($slug === '') {
                    $fail('Use at least one letter or number.');
                } elseif (Tag::query()->where('slug', $slug)->when($tag, fn ($q) => $q->whereKeyNot($tag->id))->exists()) {
                    $fail('A tag with this name already exists.');
                }
            }],
        ]);

        return Str::squish($request->string('name'));
    }
}
