<?php

namespace App\Support;

use App\Models\Area;
use Illuminate\Support\Collection;

/**
 * Geometry for the home hero's map of North Texas: active cities placed by their
 * real coordinates, with the main highways and lakes drawn behind them. The
 * highways are simplified outlines for decoration, not navigation.
 */
class RouteMap
{
    public const WIDTH = 840;

    public const HEIGHT = 720;

    /** Visible area: [north, south, west, east]. 840 × 720 keeps roughly true proportions at this latitude. */
    public const BOUNDS = [33.32, 32.6, -97.5, -96.5];

    /** @var array<string, list<array{0: float, 1: float}>> [lng, lat] points */
    private const ROADS = [
        'I-30' => [[-97.5, 32.74], [-97.33, 32.74], [-97.11, 32.76], [-96.99, 32.76], [-96.8, 32.78], [-96.65, 32.82], [-96.5, 32.88]],
        'I-20' => [[-97.5, 32.67], [-97.33, 32.67], [-97.1, 32.67], [-96.95, 32.66], [-96.75, 32.67], [-96.5, 32.69]],
        'I-35E' => [[-96.8, 32.6], [-96.81, 32.78], [-96.9, 32.9], [-96.99, 33.02], [-97.03, 33.1], [-97.12, 33.2], [-97.15, 33.32]],
        'I-35W' => [[-97.32, 32.6], [-97.32, 32.75], [-97.31, 32.9], [-97.3, 33.0], [-97.2, 33.12], [-97.13, 33.21]],
        'US-75' => [[-96.8, 32.78], [-96.77, 32.9], [-96.72, 33.0], [-96.68, 33.1], [-96.62, 33.2], [-96.6, 33.32]],
        'DNT' => [[-96.8, 32.8], [-96.82, 32.95], [-96.82, 33.1], [-96.83, 33.32]],
        'I-635' => [[-96.97, 32.9], [-96.85, 32.93], [-96.75, 32.92], [-96.65, 32.87], [-96.62, 32.78], [-96.65, 32.68]],
        'SH-121' => [[-97.1, 32.87], [-96.99, 32.98], [-96.83, 33.1], [-96.7, 33.17], [-96.6, 33.22]],
        'I-820' => [[-97.22, 32.67], [-97.21, 32.82], [-97.33, 32.87], [-97.45, 32.8], [-97.44, 32.67]],
    ];

    /** @var list<list<array{0: float, 1: float}>> */
    private const LAKES = [
        [[-97.06, 33.08], [-97.0, 33.08], [-96.96, 33.13], [-96.98, 33.19], [-97.04, 33.17], [-97.07, 33.12]], // Lewisville
        [[-96.55, 32.8], [-96.5, 32.8], [-96.49, 32.9], [-96.53, 32.93], [-96.56, 32.87]], // Ray Hubbard
        [[-97.04, 32.62], [-96.96, 32.61], [-96.97, 32.65], [-97.03, 32.66]], // Joe Pool
        [[-97.19, 32.91], [-97.1, 32.91], [-97.09, 32.97], [-97.16, 32.97]], // Grapevine
    ];

    /** Label side where the default (right) would run into a neighbor or the edge. */
    private const LABEL_SIDES = ['fort-worth-tx' => 'left', 'arlington-tx' => 'left', 'grand-prairie-tx' => 'below', 'irving-tx' => 'above', 'denton-tx' => 'left', 'mckinney-tx' => 'left', 'garland-tx' => 'below'];

    /** @return array{0: float, 1: float} */
    public static function project(float $lng, float $lat): array
    {
        [$north, $south, $west, $east] = self::BOUNDS;

        return [
            round(($lng - $west) / ($east - $west) * self::WIDTH, 1),
            round(($north - $lat) / ($north - $south) * self::HEIGHT, 1),
        ];
    }

    /** @return list<string> SVG polyline point lists */
    public static function roads(): array
    {
        return array_values(array_map(self::points(...), self::ROADS));
    }

    /** @return list<string> SVG polygon point lists */
    public static function lakes(): array
    {
        return array_map(self::points(...), self::LAKES);
    }

    /**
     * Active cities inside the map.
     *
     * @return Collection<int, array{slug: string, name: string, x: float, y: float, side: string}>
     */
    public static function cities(): Collection
    {
        [$north, $south, $west, $east] = self::BOUNDS;

        return Area::query()->active()->ordered()->whereNotNull('lat')->whereNotNull('lng')->get()
            ->filter(fn (Area $a) => $a->lat <= $north && $a->lat >= $south && $a->lng >= $west && $a->lng <= $east)
            ->map(function (Area $a) {
                [$x, $y] = self::project((float) $a->lng, (float) $a->lat);

                return ['slug' => $a->slug, 'name' => $a->city, 'x' => $x, 'y' => $y, 'side' => self::LABEL_SIDES[$a->slug] ?? 'right'];
            })
            ->values();
    }

    /**
     * A gentle arc between two points (the same curve the browser draws for a chosen route).
     *
     * @param  array{x: float, y: float}  $from
     * @param  array{x: float, y: float}  $to
     */
    public static function arc(array $from, array $to): string
    {
        $midX = ($from['x'] + $to['x']) / 2 + ($to['y'] - $from['y']) * 0.18;
        $midY = ($from['y'] + $to['y']) / 2 - ($to['x'] - $from['x']) * 0.18;

        return sprintf('M%s %s Q%s %s %s %s', $from['x'], $from['y'], round($midX, 1), round($midY, 1), $to['x'], $to['y']);
    }

    /** @param  list<array{0: float, 1: float}>  $points */
    private static function points(array $points): string
    {
        return implode(' ', array_map(fn (array $p) => implode(',', self::project($p[0], $p[1])), $points));
    }
}
