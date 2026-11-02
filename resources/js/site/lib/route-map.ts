/**
 * Draws the route on the hero's map (components/site/route-map.blade.php): one line
 * and a truck travelling along it. Places beyond the map are pinned to its edge, in
 * the right direction, so a long-distance move still reads correctly.
 */

type Point = { x: number; y: number };
export type Stop = { lat: number; lng: number; label: string };

const WIDTH = 840;
const HEIGHT = 720;
const EDGE = 120;
const DURATION = 1500;

export type RouteMap = {
    /** Update the chosen stops; null clears one. With both, the route draws between them. */
    setStops(from: Stop | null, to: Stop | null): void;
    /** Play the sample route already in the markup. */
    play(): void;
};

export function routeMap(svg: SVGSVGElement): RouteMap | null {
    const q = <T extends Element>(name: string) => svg.querySelector<T>(`[data-route-${name}]`);
    const line = q<SVGPathElement>('line');
    const glow = q<SVGPathElement>('glow');
    const fromPin = q<SVGCircleElement>('from');
    const toPin = q<SVGCircleElement>('to');
    const fromLabel = q<SVGTextElement>('from-label');
    const toLabel = q<SVGTextElement>('to-label');
    const truck = q<SVGGElement>('truck');

    if (!line || !glow || !fromPin || !toPin || !fromLabel || !toLabel || !truck) return null;

    const [north, south, west, east] = (svg.dataset.bounds ?? '').split(',').map(Number);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const sample = { d: line.getAttribute('d') ?? '', from: pinPoint(fromPin), to: pinPoint(toPin) };
    let frame = 0;

    function pinPoint(pin: SVGCircleElement): Point {
        return { x: Number(pin.getAttribute('cx')), y: Number(pin.getAttribute('cy')) };
    }

    /** Map position; anything outside is moved along the line from the centre to the edge. */
    function project(stop: Stop): Point {
        const x = ((stop.lng - west) / (east - west)) * WIDTH;
        const y = ((north - stop.lat) / (north - south)) * HEIGHT;
        const cx = WIDTH / 2;
        const cy = HEIGHT / 2;
        const dx = x - cx;
        const dy = y - cy;
        const scale = Math.min(1, (cx - EDGE) / Math.max(Math.abs(dx), 1), (cy - EDGE) / Math.max(Math.abs(dy), 1));

        return { x: cx + dx * scale, y: cy + dy * scale };
    }

    function place(pin: SVGCircleElement, label: SVGTextElement, at: Point | null, text: string): void {
        pin.style.display = at ? '' : 'none';
        label.textContent = at ? text : '';
        if (!at) return;

        pin.setAttribute('cx', String(at.x));
        pin.setAttribute('cy', String(at.y));
        // Keep the label on the map; above the pin unless that is off the top.
        label.setAttribute('x', String(Math.min(WIDTH - 90, Math.max(90, at.x))));
        label.setAttribute('y', String(at.y < 110 ? at.y + 44 : at.y - 72));
    }

    /** A city's name stays hidden while a chosen place's name sits on top of it. */
    function clearLabels(): void {
        const pins = [fromLabel!, toLabel!].filter((l) => l.textContent).map((l) => l.getBBox());

        svg.querySelectorAll<SVGTextElement>('.route-map-cities text').forEach((city) => {
            const c = city.getBBox();
            const covered = pins.some((p) => p.x < c.x + c.width && c.x < p.x + p.width && p.y < c.y + c.height && c.y < p.y + p.height);
            city.style.visibility = covered ? 'hidden' : '';
        });
    }

    function arc(a: Point, b: Point): string {
        const mx = (a.x + b.x) / 2 + (b.y - a.y) * 0.18;
        const my = (a.y + b.y) / 2 - (b.x - a.x) * 0.18;

        return `M${a.x} ${a.y} Q${mx} ${my} ${b.x} ${b.y}`;
    }

    function moveTruck(at: DOMPoint | Point): void {
        truck!.setAttribute('transform', `translate(${at.x} ${at.y})`);
    }

    function setProgress(length: number, t: number): void {
        const offset = String(length * (1 - t));
        line!.style.strokeDasharray = glow!.style.strokeDasharray = String(length);
        line!.style.strokeDashoffset = glow!.style.strokeDashoffset = offset;
        moveTruck(line!.getPointAtLength(length * t));
    }

    function draw(d: string): void {
        cancelAnimationFrame(frame);
        line!.setAttribute('d', d);
        glow!.setAttribute('d', d);
        line!.style.display = glow!.style.display = truck!.style.display = '';

        const length = line!.getTotalLength();

        // Browsers pause animation in background tabs: show the finished route instead of an empty map.
        if (reduced || document.hidden) return setProgress(length, 1);

        const start = performance.now();
        const step = (now: number) => {
            const p = Math.min(1, (now - start) / DURATION);
            setProgress(length, 1 - (1 - p) ** 3); // eases to a stop at the door
            if (p < 1) frame = requestAnimationFrame(step);
        };

        setProgress(length, 0);
        frame = requestAnimationFrame(step);
    }

    return {
        play: () => draw(sample.d),
        setStops(from, to) {
            cancelAnimationFrame(frame);

            if (!from && !to) {
                place(fromPin, fromLabel, sample.from, '');
                place(toPin, toLabel, sample.to, '');
                clearLabels();
                return draw(sample.d);
            }

            const a = from ? project(from) : null;
            const b = to ? project(to) : null;

            place(fromPin, fromLabel, a, from?.label ?? '');
            place(toPin, toLabel, b, to?.label ?? '');
            clearLabels();

            if (a && b) return draw(arc(a, b));

            // One stop so far: just its pin; the route waits for the other.
            line.style.display = glow.style.display = truck.style.display = 'none';
        },
    };
}
