<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewSource;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer reviews, shown on /reviews, in review sliders and on the linked
 * city and service pages.
 */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::enum(ReviewSource::class)],
            'area' => ['nullable', 'integer'],
        ]);

        $reviews = Review::query()
            ->with(['area', 'service', 'photo.variants'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('body', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->ordered()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'city' => $r->city,
                'rating' => $r->rating,
                'body' => $r->body,
                'source' => $r->source->label(),
                'reviewed_at' => $r->reviewed_at?->format('M j, Y'),
                'area' => $r->area?->name,
                'service' => $r->service?->title,
                'is_featured' => $r->is_featured,
                'is_active' => $r->is_active,
                'is_sample' => $r->is_sample,
                'thumb' => $r->photo?->url(240),
            ]);

        $active = Review::query()->active();

        return Inertia::render('reviews/Index', [
            'reviews' => $reviews,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'source' => $filters['source'] ?? '',
                'area' => isset($filters['area']) ? (string) $filters['area'] : '',
            ],
            'sources' => ReviewSource::options(),
            'areas' => Area::query()->ordered()->get()->map(fn (Area $a) => ['id' => $a->id, 'name' => $a->name]),
            'stats' => [
                'samples' => Review::query()->where('is_sample', true)->count(),
                'count' => (clone $active)->count(),
                'average' => round((float) (clone $active)->avg('rating'), 1),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('reviews/Form', $this->formProps(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $review = Review::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Review from {$review->name} added."]);

        return to_route('admin.reviews.index');
    }

    public function edit(Review $review): Response
    {
        return Inertia::render('reviews/Form', $this->formProps($review));
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $review->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review saved.']);

        return to_route('admin.reviews.edit', $review);
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Review from {$review->name} deleted."]);

        return to_route('admin.reviews.index');
    }

    /** Quick on/off switches from the list. */
    public function toggle(Request $request, Review $review): RedirectResponse
    {
        $field = $request->validate(['field' => ['required', Rule::in(['is_active', 'is_featured'])]])['field'];

        $review->update([$field => ! $review->{$field}]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Review $review): array
    {
        return [
            'review' => $review ? [
                ...$review->only(['id', 'name', 'city', 'rating', 'body', 'photo_media_id', 'area_id', 'service_id', 'is_featured', 'is_active']),
                'source' => $review->source->value,
                'reviewed_at' => $review->reviewed_at?->format('Y-m-d'),
            ] : null,
            'sources' => ReviewSource::options(),
            'areas' => Area::query()->ordered()->get()->map(fn (Area $a) => ['id' => $a->id, 'name' => $a->name]),
            'services' => Service::query()->ordered()->get(['id', 'title'])->map(fn (Service $s) => ['id' => $s->id, 'name' => $s->title]),
            'today' => now()->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'max:3000'],
            'source' => ['required', Rule::enum(ReviewSource::class)],
            'reviewed_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'photo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
        ], [
            'reviewed_at.before_or_equal' => 'The review date cannot be in the future.',
        ], [
            'body' => 'review text',
            'reviewed_at' => 'review date',
            'photo_media_id' => 'photo',
            'area_id' => 'city page',
            'service_id' => 'service',
        ]);

        return [
            ...$data,
            // Saved by a person in the form: no longer placeholder content.
            'is_sample' => false,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
