<?php

namespace App\Sections;

use App\Models\Area;
use App\Models\Faq;
use App\Models\MoveType;
use App\Models\Review;
use App\Models\Section;
use App\Models\Service;
use App\Services\TokenReplacer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Renders a record's visible sections with resources/views/sections/{type}.blade.php.
 *
 *   $rendered = $renderer->render($page);                       // home, about…
 *   $rendered = $renderer->render($asp, area: $area, service: $service);
 *
 *   @unless ($rendered->hasH1) <h1>{{ $page->title }}</h1> @endunless
 *
 * The first visible hero's heading becomes the page H1; every other section
 * heading is an H2, so each page has exactly one H1.
 */
class SectionRenderer
{
    public function __construct(private readonly SectionRegistry $registry) {}

    /**
     * @param  Model  $owner  any model using HasSections
     * @param  iterable<Section>|null  $sections  defaults to the owner's visible sections
     *                                            (pass the template's sections for area × service pages)
     */
    public function render(
        Model $owner,
        ?Area $area = null,
        ?Service $service = null,
        ?iterable $sections = null,
        bool $allowH1 = true,
    ): RenderedSections {
        $tokens = new TokenReplacer($area, $service);
        $sections ??= $owner->visibleSections()->get();
        $html = '';
        $hasH1 = false;
        $faqs = new Collection;

        foreach ($sections as $section) {
            if (! $section->is_visible || ! $this->registry->exists($section->type)) {
                continue;
            }

            $definition = $this->registry->definition($section->type);
            $data = $this->applyTokens(
                $definition['fields'],
                array_replace($this->registry->defaults($section->type), $section->data ?? []),
                $tokens,
            );

            $isH1 = $allowH1 && ! $hasH1 && $section->type === 'hero' && filled($data['heading'] ?? null);
            $hasH1 = $hasH1 || $isH1;

            $items = $this->resolveItems($section->type, $data, $owner, $area, $service);

            if ($section->type === 'faq') {
                $faqs = $faqs->merge($items);
            }

            $html .= view("sections.{$section->type}", [
                's' => $data,
                'section' => $section,
                'items' => $items,
                'headingTag' => $isH1 ? 'h1' : 'h2',
                'anchor' => "section-{$section->id}",
                'area' => $area,
                'service' => $service,
                'owner' => $owner,
            ])->render();
        }

        return new RenderedSections($html, $hasH1, $faqs->unique('id')->values());
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyTokens(array $fields, array $data, TokenReplacer $tokens): array
    {
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $data[$key] ?? null;

            $data[$key] = match ($field['type']) {
                'text', 'textarea' => $tokens->replace($value),
                'richtext' => $tokens->replaceHtml($value),
                'link' => is_array($value) ? [...$value, 'label' => $tokens->replace($value['label'] ?? null)] : $value,
                'repeater' => is_array($value)
                    ? array_map(fn ($row) => $this->applyTokens($field['fields'], (array) $row, $tokens), $value)
                    : [],
                default => $value,
            };
        }

        return $data;
    }

    /**
     * Records a section lists (services, areas, reviews, FAQs, move types).
     */
    private function resolveItems(string $type, array $data, Model $owner, ?Area $area, ?Service $service): Collection
    {
        $mode = $data['mode'] ?? null;

        return match ($type) {
            'services_grid' => $this->ordered(
                Service::query()->active()->with(['image.variants', 'icon.variants'])
                    ->when($mode === 'home', fn (Builder $q) => $q->where('show_on_home', true))
                    ->when($mode !== 'picked', fn (Builder $q) => $q->ordered()),
                $mode === 'picked' ? $data['services'] ?? [] : null,
            ),
            'areas_grid' => $this->ordered(
                Area::query()->active()->with('image.variants')->when($mode !== 'picked', fn (Builder $q) => $q->ordered()),
                $mode === 'picked' ? $data['areas'] ?? [] : null,
            ),
            'reviews_slider' => $this->reviews($mode, $data, $area, $service),
            'faq' => $mode === 'picked'
                ? $this->ordered(Faq::query()->active(), $data['faqs'] ?? [])
                : (method_exists($owner, 'faqs') ? $owner->faqs()->active()->get() : new EloquentCollection),
            'quote_form' => MoveType::query()->active()->ordered()->get(),
            default => new Collection,
        };
    }

    private function reviews(?string $mode, array $data, ?Area $area, ?Service $service): Collection
    {
        $limit = max(1, min(12, (int) ($data['limit'] ?? 6)));
        $query = Review::query()->active()->with('photo.variants');

        if ($mode === 'picked') {
            return $this->ordered($query, $data['reviews'] ?? [])->take($limit)->values();
        }

        if ($mode === 'context' && ($area || $service)) {
            $contextual = (clone $query)
                ->when($area, fn (Builder $q) => $q->where('area_id', $area->id))
                ->when($service && ! $area, fn (Builder $q) => $q->where('service_id', $service->id))
                ->latest('reviewed_at')->limit($limit)->get();

            // Fall back to featured reviews when the city/service has none yet.
            if ($contextual->isNotEmpty()) {
                return $contextual;
            }

            $mode = 'featured';
        }

        return $query
            ->when($mode !== 'latest', fn (Builder $q) => $q->orderByDesc('is_featured'))
            ->orderBy('sort')
            ->latest('reviewed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * All matching records, or only the picked IDs in the admin's chosen order.
     *
     * @param  list<int>|null  $ids
     */
    private function ordered(Builder $query, ?array $ids): Collection
    {
        if ($ids === null) {
            return $query->get();
        }

        if ($ids === []) {
            return new EloquentCollection;
        }

        $records = $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $records->get($id))->filter()->values();
    }
}
