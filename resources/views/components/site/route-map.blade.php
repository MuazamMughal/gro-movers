{{--
    Map of the cities we serve, for the home hero. Without JS it shows a sample route;
    the quick-quote island redraws the route when the visitor chooses their addresses.
--}}
@php
    use App\Support\RouteMap;

    $cities = RouteMap::cities();
    $from = $cities->firstWhere('slug', 'fort-worth-tx') ?? $cities->first();
    $to = $cities->firstWhere('slug', 'plano-tx') ?? $cities->last();
    $route = $from && $to && $from !== $to ? RouteMap::arc($from, $to) : '';
    [$north, $south, $west, $east] = RouteMap::BOUNDS;
@endphp
<svg
    {{ $attributes->class('route-map block h-auto w-full') }}
    viewBox="0 0 {{ RouteMap::WIDTH }} {{ RouteMap::HEIGHT }}"
    role="img"
    aria-label="Map of the Dallas–Fort Worth area showing the cities we serve: {{ $cities->pluck('name')->implode(', ') }}"
    data-route-map
    data-bounds="{{ implode(',', [$north, $south, $west, $east]) }}"
    @if ($route) data-start="{{ $from['x'] }},{{ $from['y'] }}" data-end="{{ $to['x'] }},{{ $to['y'] }}" @endif
>
    <g fill="#ffffff" fill-opacity=".07">
        @foreach (RouteMap::lakes() as $lake)
            <polygon points="{{ $lake }}" />
        @endforeach
    </g>
    <g fill="none" stroke="#ffffff" stroke-opacity=".16" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        @foreach (RouteMap::roads() as $road)
            <polyline points="{{ $road }}" />
        @endforeach
    </g>
    <g class="route-map-cities">
        @foreach ($cities as $city)
            @php
                [$labelX, $labelY, $anchor] = match ($city['side']) {
                    'left' => [$city['x'] - 18, $city['y'] + 7, 'end'],
                    'above' => [$city['x'], $city['y'] - 14, 'middle'],
                    'below' => [$city['x'], $city['y'] + 28, 'middle'],
                    default => [$city['x'] + 18, $city['y'] + 7, 'start'],
                };
            @endphp
            <circle cx="{{ $city['x'] }}" cy="{{ $city['y'] }}" r="6" fill="#ffffff" fill-opacity=".9" />
            <text x="{{ $labelX }}" y="{{ $labelY }}" text-anchor="{{ $anchor }}">{{ $city['name'] }}</text>
        @endforeach
    </g>
    @if ($route)
        <path data-route-glow d="{{ $route }}" fill="none" stroke="var(--color-tertiary)" stroke-opacity=".28" stroke-width="16" stroke-linecap="round" />
        <path data-route-line d="{{ $route }}" fill="none" stroke="var(--color-tertiary)" stroke-width="5" stroke-linecap="round" />
        <circle data-route-from cx="{{ $from['x'] }}" cy="{{ $from['y'] }}" r="11" fill="#ffffff" stroke="var(--color-tertiary)" stroke-width="5" />
        <circle data-route-to cx="{{ $to['x'] }}" cy="{{ $to['y'] }}" r="12" fill="var(--color-tertiary)" stroke="#ffffff" stroke-width="4" />
        <text data-route-from-label class="route-map-pin-label" text-anchor="middle"></text>
        <text data-route-to-label class="route-map-pin-label" text-anchor="middle"></text>
        <g data-route-truck transform="translate({{ $to['x'] }} {{ $to['y'] }})">
            <g data-route-truck-body transform="translate(0 -38)">
                <circle r="23" fill="#ffffff" />
                <g transform="translate(-13 -13) scale(1.08)" fill="none" stroke="var(--color-secondary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2" /><path d="M15 18H9" /><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14" /><circle cx="17" cy="18" r="2" /><circle cx="7" cy="18" r="2" />
                </g>
            </g>
        </g>
    @endif
</svg>
