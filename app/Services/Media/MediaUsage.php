<?php

namespace App\Services\Media;

use App\Models\Area;
use App\Models\Author;
use App\Models\Media;
use App\Models\Post;
use App\Models\Review;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Settings\SettingsTabs;
use App\Support\Settings;
use Closure;
use Illuminate\Support\Arr;

/**
 * Finds every place a media item is used, so it cannot be deleted while in use.
 *
 * Built-in sources are media foreign keys and media settings. Modules that store
 * media IDs elsewhere (section JSON in T5, rich text in T9) add a resolver with
 * MediaUsage::resolveUsing(fn (Media $media): array => [...]).
 */
class MediaUsage
{
    /** @var list<Closure(Media): list<array{label: string, field: string}>> */
    private static array $resolvers = [];

    /**
     * Columns holding a media FK: model => [column => field label].
     *
     * @var array<class-string, array<string, string>>
     */
    public const COLUMNS = [
        Service::class => ['icon_media_id' => 'Icon', 'image_media_id' => 'Card image', 'hero_media_id' => 'Hero image'],
        Area::class => ['hero_media_id' => 'Hero image', 'image_media_id' => 'Card image'],
        Post::class => ['featured_media_id' => 'Featured image'],
        Author::class => ['photo_media_id' => 'Photo'],
        Review::class => ['photo_media_id' => 'Photo'],
    ];

    /**
     * @param  Closure(Media): list<array{label: string, field: string}>  $resolver
     */
    public static function resolveUsing(Closure $resolver): void
    {
        static::$resolvers[] = $resolver;
    }

    /**
     * @return list<array{label: string, field: string}>
     */
    public function find(Media $media): array
    {
        $usages = [];

        foreach (static::COLUMNS as $model => $columns) {
            foreach ($columns as $column => $field) {
                $query = $model::query()->where($column, $media->id);

                if (method_exists($model, 'bootSoftDeletes')) {
                    $query->withTrashed();
                }

                foreach ($query->get() as $record) {
                    $usages[] = ['label' => $this->describe($record), 'field' => $field];
                }
            }
        }

        foreach (SeoMeta::query()->where('og_image_id', $media->id)->with('seoable')->get() as $seo) {
            $usages[] = [
                'label' => $seo->seoable ? $this->describe($seo->seoable) : 'Deleted record',
                'field' => 'Social share image',
            ];
        }

        foreach ($this->mediaSettings() as $key => $label) {
            if ((int) Settings::get($key) === $media->id) {
                $usages[] = ['label' => 'Settings', 'field' => $label];
            }
        }

        foreach (static::$resolvers as $resolver) {
            array_push($usages, ...$resolver($media));
        }

        return $usages;
    }

    public function isUsed(Media $media): bool
    {
        return $this->find($media) !== [];
    }

    /**
     * Every settings key of type "media", with its label.
     *
     * @return array<string, string>
     */
    private function mediaSettings(): array
    {
        $fields = collect(SettingsTabs::all())->flatMap(fn (array $tab) => $tab['fields']);

        return Arr::mapWithKeys(
            $fields->where('type', 'media')->values()->all(),
            fn (array $field) => [$field['key'] => $field['label']],
        );
    }

    private function describe(object $record): string
    {
        $type = class_basename($record);
        $name = $record->title ?? $record->name ?? ($record->city ?? null) ?? "#{$record->id}";

        return "{$type}: {$name}";
    }
}
