<?php

namespace App\Seo;

use App\Support\SeoForm;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The SEO report: indexable URLs that need attention (fallback-only SEO,
 * titles or descriptions too long or short), duplicates across indexable
 * URLs, and pages hidden from Google.
 */
class SeoAudit
{
    public const TITLE_MAX = SeoForm::TITLE_LIMIT;       // 60

    public const DESCRIPTION_MAX = SeoForm::DESCRIPTION_LIMIT; // 160

    public const DESCRIPTION_MIN = 70;

    /** Pages that are noindex on purpose (not counted as "hidden by mistake"). */
    public const NOINDEX_EXPECTED = ['/thank-you'];

    /**
     * @return array{entries: list<array<string, mixed>>, duplicates: list<array<string, mixed>>, hidden: list<array<string, mixed>>, summary: array<string, int>}
     */
    public static function run(): array
    {
        $all = SiteIndex::all();
        $indexable = SiteIndex::indexable();
        $duplicates = static::duplicates($indexable);
        $dupPaths = collect($duplicates)->flatMap(fn ($d) => array_column($d['urls'], 'path'))->countBy();

        $entries = $indexable->map(function (SiteUrl $u) use ($dupPaths) {
            $issues = static::issues($u);

            if ($dupPaths->has($u->path)) {
                $issues[] = ['code' => 'duplicate', 'message' => 'Shares its title, description or H1 with another page.'];
            }

            return [...$u->toArray(), 'issues' => $issues];
        })->values();

        $hidden = $all->reject(fn (SiteUrl $u) => $u->index && $u->selfCanonical())
            ->map(fn (SiteUrl $u) => [
                ...$u->toArray(),
                'reason' => ! $u->index ? 'noindex' : 'canonical points to '.$u->canonical,
                'expected' => in_array($u->path, static::NOINDEX_EXPECTED, true),
            ])->values();

        return [
            'entries' => $entries->all(),
            'duplicates' => $duplicates,
            'hidden' => $hidden->all(),
            'summary' => [
                'urls' => $indexable->count(),
                'attention' => $entries->filter(fn ($e) => $e['issues'] !== [])->count(),
                'duplicates' => count($duplicates),
                'hidden' => $hidden->count(),
                'hidden_unexpected' => $hidden->where('expected', false)->count(),
            ],
        ];
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    public static function issues(SiteUrl $u): array
    {
        $issues = [];
        $titleLength = Str::length($u->title);
        $descriptionLength = Str::length((string) $u->description);

        if (! $u->customTitle) {
            $issues[] = ['code' => 'title_fallback', 'message' => 'No custom meta title (uses the automatic one).'];
        }

        if (! $u->customDescription) {
            $issues[] = ['code' => 'description_fallback', 'message' => 'No custom meta description (uses the automatic one).'];
        }

        if ($titleLength > static::TITLE_MAX) {
            $issues[] = ['code' => 'title_long', 'message' => "Title is {$titleLength} characters; Google shows about ".static::TITLE_MAX.'.'];
        }

        if ($descriptionLength === 0) {
            $issues[] = ['code' => 'description_empty', 'message' => 'No meta description at all.'];
        } elseif ($descriptionLength > static::DESCRIPTION_MAX) {
            $issues[] = ['code' => 'description_long', 'message' => "Description is {$descriptionLength} characters; Google shows about ".static::DESCRIPTION_MAX.'.'];
        } elseif ($descriptionLength < static::DESCRIPTION_MIN) {
            $issues[] = ['code' => 'description_short', 'message' => "Description is only {$descriptionLength} characters; aim for 120–160."];
        }

        if ($u->h1 === '') {
            $issues[] = ['code' => 'h1_missing', 'message' => 'The page has no H1 heading.'];
        }

        return $issues;
    }

    /**
     * Groups of indexable URLs sharing a title, description or H1.
     *
     * @param  Collection<int, SiteUrl>  $urls
     * @return list<array{field: string, value: string, urls: list<array<string, string>>}>
     */
    public static function duplicates(Collection $urls): array
    {
        $groups = [];

        foreach (['title' => 'Meta title', 'description' => 'Meta description', 'h1' => 'H1'] as $field => $label) {
            $urls->filter(fn (SiteUrl $u) => filled($u->{$field}))
                ->groupBy(fn (SiteUrl $u) => static::normalize($u->{$field}))
                ->filter(fn (Collection $group) => $group->count() > 1)
                ->each(function (Collection $group) use (&$groups, $field, $label) {
                    $groups[] = [
                        'field' => $field,
                        'label' => $label,
                        'value' => $group->first()->{$field},
                        'urls' => $group->map(fn (SiteUrl $u) => ['path' => $u->path, 'label' => $u->label, 'edit_url' => $u->editUrl])->values()->all(),
                    ];
                });
        }

        return $groups;
    }

    /**
     * Indexable URLs (other than $exceptPath) that already use these values.
     * Powers the live warning in the admin SEO panel.
     *
     * @return list<array{field: string, label: string, path: string, edit_url: string}>
     */
    public static function clashes(?string $exceptPath, ?string $title, ?string $description, ?string $h1 = null): array
    {
        $checks = array_filter(['title' => $title, 'description' => $description, 'h1' => $h1], 'filled');
        $clashes = [];

        foreach (SiteIndex::indexable() as $u) {
            if ($exceptPath !== null && rtrim($u->path, '/') === rtrim($exceptPath, '/')) {
                continue;
            }

            foreach ($checks as $field => $value) {
                if (static::normalize($u->{$field} ?? '') === static::normalize($value)) {
                    $clashes[] = ['field' => $field, 'label' => $u->label, 'path' => $u->path, 'edit_url' => $u->editUrl];
                }
            }
        }

        return $clashes;
    }

    private static function normalize(?string $value): string
    {
        return Str::lower(Str::squish((string) $value));
    }
}
