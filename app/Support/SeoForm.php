<?php

namespace App\Support;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The admin SEO tab shared by every content form (pages, services, areas,
 * area × service, posts, categories): validation rules, the props the Vue
 * SeoPanel needs, and saving.
 *
 *   $request->validate([...$own, ...SeoForm::rules()]);
 *   SeoForm::save($service, $request->input('seo', []));
 *   Inertia::render('services/Form', ['seo' => SeoForm::props($service, $service->publicPath())]);
 */
class SeoForm
{
    public const FIELDS = [
        'meta_title', 'meta_description', 'canonical_url', 'og_title',
        'og_description', 'og_image_id', 'robots_index', 'robots_follow',
    ];

    // Soft limits shown in the panel; the hard limits below only stop abuse.
    public const TITLE_LIMIT = 60;

    public const DESCRIPTION_LIMIT = 160;

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'seo'): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.meta_title" => ['nullable', 'string', 'max:120'],
            "{$prefix}.meta_description" => ['nullable', 'string', 'max:320'],
            // Either a site path ("/services/local-moving") or a full https URL.
            "{$prefix}.canonical_url" => ['nullable', 'string', 'max:255', 'regex:#^(/[^\s]*|https?://[^\s]+)$#'],
            "{$prefix}.og_title" => ['nullable', 'string', 'max:120'],
            "{$prefix}.og_description" => ['nullable', 'string', 'max:320'],
            "{$prefix}.og_image_id" => ['nullable', 'integer', 'exists:media,id'],
            "{$prefix}.robots_index" => ['boolean'],
            "{$prefix}.robots_follow" => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $prefix = 'seo'): array
    {
        return [
            "{$prefix}.meta_title" => 'meta title',
            "{$prefix}.meta_description" => 'meta description',
            "{$prefix}.canonical_url" => 'canonical URL',
            "{$prefix}.og_title" => 'social title',
            "{$prefix}.og_description" => 'social description',
            "{$prefix}.og_image_id" => 'social share image',
        ];
    }

    /**
     * @param  Model  $model  any model using HasSeo
     * @param  array<string, mixed>  $data  the validated "seo" array
     */
    public static function save(Model $model, array $data): SeoMeta
    {
        $values = [];

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;

            $values[$field] = match ($field) {
                'robots_index', 'robots_follow' => array_key_exists($field, $data) ? (bool) $value : true,
                'og_image_id' => $value ? (int) $value : null,
                default => is_string($value) && trim($value) !== '' ? Str::squish($value) : null,
            };
        }

        $seo = $model->seo()->updateOrCreate([], $values);
        $model->setRelation('seo', $seo);

        return $seo;
    }

    /**
     * Current values plus what the panel needs to preview fallbacks.
     *
     * @param  Model|null  $model  null while creating a new record
     * @return array<string, mixed>
     */
    public static function props(?Model $model, ?string $path): array
    {
        $meta = $model?->seo;

        return [
            'values' => [
                ...Arr::mapWithKeys(self::FIELDS, fn (string $f) => [$f => $meta?->{$f}]),
                'robots_index' => $meta?->robots_index ?? true,
                'robots_follow' => $meta?->robots_follow ?? true,
            ],
            'fallbacks' => $model?->seoFallbacks() ?? [
                'title' => null,
                'description' => Settings::get('seo.default_description'),
                'image_id' => Settings::get('seo.default_og_image_id'),
            ],
            'url' => $path === null ? null : Seo::absoluteUrl($path),
        ];
    }

    /**
     * Shared settings the panel uses on every form (title suffix, limits, site name).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'title_suffix' => (string) Settings::get('seo.title_suffix', ''),
            'default_description' => Settings::get('seo.default_description'),
            'default_image_id' => Settings::get('seo.default_og_image_id'),
            'site_name' => Seo::siteName(),
            'site_url' => Seo::absoluteUrl('/'),
            'title_limit' => self::TITLE_LIMIT,
            'description_limit' => self::DESCRIPTION_LIMIT,
        ];
    }
}
