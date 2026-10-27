<?php

namespace App\Seo;

use App\Enums\ReviewSource;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Post;
use App\Models\Review;
use App\Models\Service;
use App\Support\Contact;
use App\Support\Seo;
use App\Support\Settings;
use App\Support\SiteTime;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * JSON-LD for public pages, printed as one @graph per page.
 *
 * Every page: MovingCompany (a LocalBusiness subtype, one real address only)
 * and WebSite. Pages add their own nodes: Service, BlogPosting, FAQPage,
 * BreadcrumbList, and on /reviews the AggregateRating from on-site reviews.
 */
class Schema
{
    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function graph(array $nodes): array
    {
        return ['@context' => 'https://schema.org', '@graph' => array_values(array_filter([static::business(), static::website(), ...$nodes]))];
    }

    public static function businessId(): string
    {
        return Seo::absoluteUrl('/').'#business';
    }

    /**
     * @param  array<string, mixed>  $extra  merged into the node (e.g. aggregateRating on /reviews)
     * @return array<string, mixed>
     */
    public static function business(array $extra = []): array
    {
        $g = Settings::group('general');
        $logo = static::image(Settings::get('branding.logo_light_id'));
        $image = static::image(Settings::get('seo.default_og_image_id')) ?? $logo;

        return array_filter([
            '@type' => 'MovingCompany',
            '@id' => static::businessId(),
            'name' => $g['company_name'] ?? null,
            'legalName' => $g['legal_name'] ?? null,
            'url' => Seo::absoluteUrl('/'),
            'logo' => $logo ? ['@type' => 'ImageObject', 'url' => $logo['url'], 'width' => $logo['width'], 'height' => $logo['height']] : null,
            'image' => $image['url'] ?? null,
            'telephone' => Contact::phone() ? substr(Contact::telHref(), 4) : null,
            'email' => Contact::email() ?: null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $g['address_street'] ?? null,
                'addressLocality' => $g['address_city'] ?? null,
                'addressRegion' => $g['address_state'] ?? null,
                'postalCode' => $g['address_zip'] ?? null,
                'addressCountry' => 'US',
            ]),
            'geo' => filled($g['lat'] ?? null) && filled($g['lng'] ?? null)
                ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $g['lat'], 'longitude' => (float) $g['lng']]
                : null,
            'openingHoursSpecification' => static::openingHours(Contact::hours()) ?: null,
            'areaServed' => static::areasServed() ?: null,
            'priceRange' => $g['price_range'] ?? null,
            'foundingDate' => filled($g['founded_year'] ?? null) ? (string) $g['founded_year'] : null,
            'sameAs' => array_values(array_filter((array) ($g['social'] ?? []))) ?: null,
            'identifier' => array_values(array_filter([
                filled($g['usdot_number'] ?? null) ? ['@type' => 'PropertyValue', 'propertyID' => 'USDOT', 'value' => (string) $g['usdot_number']] : null,
                filled($g['mc_number'] ?? null) ? ['@type' => 'PropertyValue', 'propertyID' => 'MC', 'value' => (string) $g['mc_number']] : null,
            ])) ?: null,
            'hasOfferCatalog' => static::offerCatalog(),
            ...$extra,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** @return array<string, mixed> */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => Seo::absoluteUrl('/').'#website',
            'url' => Seo::absoluteUrl('/'),
            'name' => Seo::siteName(),
            'publisher' => ['@id' => static::businessId()],
            'inLanguage' => 'en-US',
        ];
    }

    /** @return array<string, mixed> */
    public static function service(Service $service): array
    {
        return array_filter([
            '@type' => 'Service',
            '@id' => Seo::absoluteUrl($service->publicPath()).'#service',
            'name' => $service->title,
            'serviceType' => $service->title,
            'description' => $service->short_description ? Str::squish($service->short_description) : null,
            'url' => Seo::absoluteUrl($service->publicPath()),
            'image' => static::image($service->hero_media_id ?? $service->image_media_id)['url'] ?? null,
            'provider' => ['@id' => static::businessId()],
            'areaServed' => static::areasServed() ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public static function area(Area $area): array
    {
        return array_filter([
            '@type' => 'Service',
            '@id' => Seo::absoluteUrl($area->publicPath()).'#service',
            'name' => "Moving services in {$area->city}, {$area->state_code}",
            'serviceType' => 'Moving services',
            'description' => $area->intro ? Str::squish($area->intro) : null,
            'url' => Seo::absoluteUrl($area->publicPath()),
            'provider' => ['@id' => static::businessId()],
            'areaServed' => static::city($area),
        ]);
    }

    /** @return array<string, mixed> */
    public static function areaService(AreaServicePage $page): array
    {
        return array_filter([
            '@type' => 'Service',
            '@id' => Seo::absoluteUrl($page->publicPath()).'#service',
            'name' => $page->heading(),
            'serviceType' => $page->service->title,
            'description' => $page->service->short_description ? Str::squish($page->service->short_description) : null,
            'url' => Seo::absoluteUrl($page->publicPath()),
            'provider' => ['@id' => static::businessId()],
            'areaServed' => static::city($page->area),
        ]);
    }

    /** @return array<string, mixed> */
    public static function post(Post $post): array
    {
        $url = Seo::absoluteUrl($post->publicPath());
        $image = static::image($post->featured_media_id) ?? static::image(Settings::get('seo.default_og_image_id'));

        return array_filter([
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'headline' => Str::limit($post->title, 110, '', preserveWords: true),
            'description' => $post->summary(160),
            'image' => $image['url'] ?? null,
            'datePublished' => SiteTime::local($post->published_at)?->toIso8601String(),
            'dateModified' => SiteTime::local($post->updated_at ?? $post->published_at)?->toIso8601String(),
            'author' => $post->author
                ? array_filter(['@type' => 'Person', 'name' => $post->author->name, 'jobTitle' => $post->author->job_title])
                : ['@id' => static::businessId()],
            'publisher' => ['@id' => static::businessId()],
            'mainEntityOfPage' => $url,
            'articleSection' => $post->category?->name,
            'keywords' => $post->relationLoaded('tags') && $post->tags->isNotEmpty() ? $post->tags->pluck('name')->implode(', ') : null,
            'wordCount' => str_word_count(strip_tags((string) $post->content)) ?: null,
            'inLanguage' => 'en-US',
        ]);
    }

    /**
     * FAQPage for the FAQs a page shows (faq sections and attached FAQs).
     *
     * @param  iterable<Faq>  $faqs
     * @return array<string, mixed>|null
     */
    public static function faq(iterable $faqs): ?array
    {
        $items = collect($faqs)->unique('id')->map(fn (Faq $faq) => [
            '@type' => 'Question',
            'name' => $faq->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags((string) $faq->answer, '<p><br><ul><ol><li><a><strong><b><em><i>'))],
        ])->values()->all();

        return $items ? ['@type' => 'FAQPage', 'mainEntity' => $items] : null;
    }

    /**
     * @param  list<array{label: string, url: string|null}>  $crumbs
     * @return array<string, mixed>|null
     */
    public static function breadcrumbs(array $crumbs, string $currentUrl): ?array
    {
        if ($crumbs === []) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['label'],
                'item' => $crumb['url'] !== null ? Seo::absoluteUrl($crumb['url']) : $currentUrl,
            ], $crumbs, array_keys($crumbs)),
        ];
    }

    /**
     * AggregateRating (and the reviews themselves) for /reviews. Only reviews
     * collected on this website count: Google does not allow marking up
     * ratings copied from Google, Yelp or Facebook.
     *
     * @param  Collection<int, Review>  $reviews
     * @return array<string, mixed>|null merged into the business node
     */
    public static function ratings(Collection $reviews): ?array
    {
        $own = $reviews->filter(fn (Review $r) => $r->source === ReviewSource::Website)->values();

        if ($own->isEmpty()) {
            return null;
        }

        return [
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => round($own->avg('rating'), 1),
                'reviewCount' => $own->count(),
                'bestRating' => 5,
                'worstRating' => 1,
            ],
            'review' => $own->take(20)->map(fn (Review $r) => array_filter([
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $r->name],
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $r->rating, 'bestRating' => 5],
                'reviewBody' => Str::limit(Str::squish($r->body), 500),
                'datePublished' => $r->reviewed_at?->toDateString(),
            ]))->values()->all(),
        ];
    }

    /**
     * "Mon–Fri: 7:00 AM – 7:00 PM" rows → OpeningHoursSpecification. Rows that cannot
     * be read (or say "Closed") are left out.
     *
     * @param  list<array{days: string, hours: string}>  $rows
     * @return list<array<string, mixed>>
     */
    public static function openingHours(array $rows): array
    {
        $specs = [];

        foreach ($rows as $row) {
            $days = static::days((string) ($row['days'] ?? ''));
            $hours = Str::lower(trim((string) ($row['hours'] ?? '')));

            if ($days === [] || $hours === '' || str_contains($hours, 'closed')) {
                continue;
            }

            if (str_contains($hours, '24')) {
                [$opens, $closes] = ['00:00', '23:59'];
            } else {
                $parts = preg_split('/\s*(?:–|—|-|to)\s*/u', $hours);
                $opens = static::time($parts[0] ?? '');
                $closes = static::time($parts[1] ?? '');

                if (! $opens || ! $closes) {
                    continue;
                }
            }

            $specs[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days, 'opens' => $opens, 'closes' => $closes];
        }

        return $specs;
    }

    /** "Mon–Fri", "Sat", "Mon, Wed", "Monday to Saturday" → schema.org day names. */
    private static function days(string $text): array
    {
        $week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $find = function (string $part) use ($week): ?int {
            $part = Str::lower(trim($part));

            foreach ($week as $i => $day) {
                if ($part !== '' && str_starts_with(Str::lower($day), substr($part, 0, 3))) {
                    return $i;
                }
            }

            return null;
        };

        $days = [];

        foreach (preg_split('/\s*[,&\/]\s*|\s+and\s+/i', $text) as $chunk) {
            $range = preg_split('/\s*(?:–|—|-|to)\s*/u', $chunk);
            $from = $find($range[0] ?? '');
            $to = isset($range[1]) ? $find($range[1]) : $from;

            if ($from === null || $to === null) {
                continue;
            }

            for ($i = $from; ; $i = ($i + 1) % 7) {
                $days[] = $week[$i];

                if ($i === $to) {
                    break;
                }
            }
        }

        return array_values(array_unique($days));
    }

    /** "7:00 AM", "7am", "19:00" → "07:00" */
    private static function time(string $text): ?string
    {
        $text = strtoupper(str_replace(['.', ' '], '', trim($text)));

        foreach (['g:iA', 'gA', 'G:i', 'H:i'] as $format) {
            try {
                $time = CarbonImmutable::createFromFormat("!{$format}", $text);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($time !== false && $time->format($format) === $text) {
                return $time->format('H:i');
            }
        }

        return null;
    }

    /**
     * Active cities as schema.org City places (falls back to the office city).
     *
     * @return list<array<string, mixed>>
     */
    private static function areasServed(): array
    {
        return once(function () {
            $areas = Area::query()->active()->ordered()->get()->map(fn (Area $a) => static::city($a))->all();

            if ($areas === [] && filled($city = Settings::get('general.address_city'))) {
                $areas[] = ['@type' => 'City', 'name' => $city];
            }

            return $areas;
        });
    }

    /** @return array<string, mixed> */
    private static function city(Area $area): array
    {
        return array_filter([
            '@type' => 'City',
            'name' => $area->city,
            'containedInPlace' => $area->state_name ? ['@type' => 'State', 'name' => $area->state_name] : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function offerCatalog(): ?array
    {
        $services = once(fn () => Service::query()->active()->ordered()->get());

        if ($services->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'OfferCatalog',
            'name' => 'Moving services',
            'itemListElement' => $services->map(fn (Service $s) => [
                '@type' => 'Offer',
                'itemOffered' => ['@type' => 'Service', 'name' => $s->title, 'url' => Seo::absoluteUrl($s->publicPath())],
            ])->values()->all(),
        ];
    }

    /**
     * Absolute URL and size of a media item's largest JPG/PNG (social and schema prefer them).
     *
     * @return array{url: string, width: int|null, height: int|null}|null
     */
    private static function image(mixed $id): ?array
    {
        $media = filled($id) ? once(fn () => Media::query()->with('variants')->find($id)) : null;

        if (! $media) {
            return null;
        }

        $format = $media->fallbackFormat() ?? 'webp';
        $variant = $media->variants->where('format', $format)->sortBy('width')->last();

        return [
            'url' => Seo::absoluteUrl($media->url(1200, $format)),
            'width' => $variant?->width ?? $media->width,
            'height' => $variant?->height ?? $media->height,
        ];
    }
}
