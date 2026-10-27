<?php

namespace App\Sections;

use App\Models\Section;
use Illuminate\Support\Collection;

/**
 * Fills the gaps in a record's own sections so every template page is complete:
 * a hero when there is none, the attached FAQs, and a closing call to action.
 * Built in memory only; nothing is saved.
 */
class AutoSections
{
    /** The closing call to action every template page ends with. */
    public const CTA = [
        'heading' => 'Ready for a stress-free move?',
        'text' => 'Get your free, no-obligation quote today.',
        'button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
        'show_phone' => true,
    ];

    /**
     * @param  iterable<Section>  $sections  the record's visible sections
     * @param  array<string, mixed>  $hero  data for the automatic hero
     */
    public static function complete(iterable $sections, array $hero, bool $withFaq, ?array $cta = null): Collection
    {
        $list = collect($sections)->values();
        $types = $list->pluck('type');

        if (! $types->contains('hero')) {
            $list->prepend(static::make('hero', $hero, -1));
        }

        if ($withFaq && ! $types->contains('faq')) {
            $list->push(static::make('faq', ['mode' => 'attached', 'heading' => 'Frequently asked questions', 'background' => 'muted'], -2));
        }

        // Every service, area and post page ends with Call Now + Get a Free Quote.
        if (! $types->contains('cta_banner')) {
            $list->push(static::make('cta_banner', $cta ?? static::CTA, -3));
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(string $type, array $data, int $id): Section
    {
        return (new Section)->forceFill([
            // Negative IDs keep anchors unique without clashing with real sections.
            'id' => $id,
            'type' => $type,
            'data' => $data,
            'is_visible' => true,
            'sort' => 0,
        ]);
    }
}
