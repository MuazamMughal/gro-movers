<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use App\Services\Media\MediaUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Media library. The page itself is Inertia; uploads, crops and edits are JSON
 * endpoints shared by the library, the image picker and the cropper.
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly MediaUsage $usage,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('media/Index', [
            'media' => $this->query($filters)
                ->paginate(40)
                ->withQueryString()
                ->through(fn (Media $m) => $m->toAdminArray()),
            'filters' => $filters,
            'months' => $this->months(),
            'presets' => $this->presets(),
            'limits' => $this->limits(),
        ]);
    }

    /** JSON listing for the image picker. */
    public function browse(Request $request): JsonResponse
    {
        $page = $this->query($this->filters($request))->paginate(24);

        return response()->json([
            'data' => collect($page->items())->map(fn (Media $m) => $m->toAdminArray()),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    public function show(Media $media): JsonResponse
    {
        return response()->json([
            'media' => $media->load('variants')->toAdminArray(),
            'usages' => $this->usage->find($media),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('media.max_upload_kb'), 'mimes:'.implode(',', [...config('media.image_mimes'), 'svg'])],
            ...$this->cropRules(),
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->isSvgUpload($request) && ! (config("media.presets.{$data['preset']}.svg") ?? false)) {
            return $this->fail('SVG files can only be uploaded as logos.');
        }

        return $this->attempt(fn () => $this->media->upload(
            $request->file('file'),
            $data['preset'] ?? null,
            $data['crop'] ?? null,
            array_filter(['alt' => $data['alt'] ?? null]),
        ), 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $media->update($request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(['media' => $media->load('variants')->toAdminArray()]);
    }

    /** Re-crop in place (affects every page using this image). */
    public function crop(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate($this->cropRules());

        return $this->attempt(fn () => $this->media->recrop($media, $data['preset'] ?? null, $data['crop'] ?? null));
    }

    /** Cut a new image from this one's original (other uses stay as they are). */
    public function derive(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate($this->cropRules());

        return $this->attempt(fn () => $this->media->derive($media, $data['preset'] ?? null, $data['crop'] ?? null), 201);
    }

    public function replace(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('media.max_upload_kb'), 'mimes:'.implode(',', [...config('media.image_mimes'), 'svg'])],
        ]);

        return $this->attempt(fn () => $this->media->replace($media, $request->file('file')));
    }

    public function destroy(Media $media): JsonResponse
    {
        $usages = $this->usage->find($media);

        if ($usages !== []) {
            return response()->json([
                'message' => 'This image is still in use and cannot be deleted.',
                'usages' => $usages,
            ], 409);
        }

        $this->media->delete($media);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cropRules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in(array_keys(config('media.presets')))],
            'crop' => ['nullable', 'array'],
            'crop.x' => ['required_with:crop', 'numeric', 'min:0'],
            'crop.y' => ['required_with:crop', 'numeric', 'min:0'],
            'crop.w' => ['required_with:crop', 'numeric', 'min:1'],
            'crop.h' => ['required_with:crop', 'numeric', 'min:1'],
            'crop.rotate' => ['nullable', 'integer', Rule::in([0, 90, 180, 270, -90, -180, -270])],
        ];
    }

    /**
     * @param  callable(): Media  $action
     */
    private function attempt(callable $action, int $status = 200): JsonResponse
    {
        try {
            $media = $action();
        } catch (MediaException $e) {
            return $this->fail($e->getMessage());
        }

        return response()->json(['media' => $media->toAdminArray()], $status);
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 422);
    }

    private function isSvgUpload(Request $request): bool
    {
        return in_array($request->file('file')?->getMimeType(), ['image/svg+xml', 'image/svg'], true);
    }

    /**
     * @return array{q: string|null, month: string|null, type: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->trim()->value() ?: null,
            'month' => preg_match('#^\d{4}/\d{2}$#', (string) $request->query('month')) ? $request->query('month') : null,
            'type' => in_array($request->query('type'), ['image', 'svg'], true) ? $request->query('type') : null,
        ];
    }

    private function query(array $filters): Builder
    {
        return Media::query()
            ->with('variants')
            ->when($filters['q'], fn (Builder $q, string $term) => $q->where(fn (Builder $q) => $q
                ->where('filename', 'like', "%{$term}%")
                ->orWhere('alt', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")))
            ->when($filters['month'], fn (Builder $q, string $month) => $q->where('folder', $month))
            ->when($filters['type'] === 'svg', fn (Builder $q) => $q->where('mime', 'image/svg+xml'))
            ->when($filters['type'] === 'image', fn (Builder $q) => $q->where('mime', '!=', 'image/svg+xml'))
            ->latest('id');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function months(): array
    {
        return Media::query()->distinct()->orderByDesc('folder')->pluck('folder')
            ->map(fn (string $folder) => [
                'value' => $folder,
                'label' => \DateTime::createFromFormat('!Y/m', $folder)?->format('F Y') ?? $folder,
            ])
            ->all();
    }

    /**
     * @return array<string, array{label: string, width: int|null, height: int|null, svg: bool}>
     */
    private function presets(): array
    {
        return collect(config('media.presets'))
            ->map(fn (array $p) => [
                'label' => $p['label'],
                'width' => $p['width'],
                'height' => $p['height'],
                'svg' => $p['svg'] ?? false,
            ])
            ->all();
    }

    /**
     * @return array{max_kb: int, max_megapixels: int}
     */
    private function limits(): array
    {
        return ['max_kb' => config('media.max_upload_kb'), 'max_megapixels' => config('media.max_megapixels')];
    }
}
