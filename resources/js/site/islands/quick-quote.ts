import { track } from '../lib/analytics';
import { attachAddressSearch } from '../lib/address';
import type { AddressDetails } from '../lib/address';
import type { Rule } from '../lib/form';
import { addressRule, moveDateRule, readDraft, saveDraft, stopName } from '../lib/move';
import type { DraftStop } from '../lib/move';
import { routeMap } from '../lib/route-map';
import type { Stop } from '../lib/route-map';

/** First step of a quote (home hero): checks the four answers, keeps them in this tab and opens the quote page. */
export default function mount(form: HTMLFormElement): void {
    const input = (name: string) => form.querySelector<HTMLInputElement>(`[data-field="${name}"]`)!;
    const svg = form.closest('section')?.querySelector<SVGSVGElement>('[data-route-map]');
    const map = svg ? routeMap(svg) : null;
    const server = form.dataset.addressApi ?? '';

    const stops: Record<'from' | 'to', DraftStop | null> = { from: null, to: null };
    const onMap = (stop: DraftStop | null): Stop | null => (stop?.lat && stop.lng ? { lat: Number(stop.lat), lng: Number(stop.lng), label: stopName(stop) } : null);
    const redraw = () => map?.setStops(onMap(stops.from), onMap(stops.to));

    const rules: Record<string, Rule> = {
        from: addressRule('Where are you moving from?'),
        to: addressRule('Where are you moving to?'),
        date: moveDateRule(input('date')),
        type: (v) => (v ? null : 'Please choose the type of move.'),
    };

    const check = (name: string): boolean => {
        const message = rules[name](input(name).value.trim(), form);
        const slot = form.querySelector(`[data-error-for-field="${name}"]`);

        if (slot) slot.textContent = message ?? '';
        if (message) input(name).setAttribute('aria-invalid', 'true');
        else input(name).removeAttribute('aria-invalid');

        return message === null;
    };

    for (const side of ['from', 'to'] as const) {
        attachAddressSearch(
            input(side),
            server,
            (address: AddressDetails) => {
                stops[side] = address;
                check(side);
                redraw();
            },
            // Typing again: the chosen place no longer matches what is in the box.
            () => {
                if (stops[side]) {
                    stops[side] = null;
                    redraw();
                }
            },
        );
    }

    // Clear a message as soon as the field is fixed.
    form.addEventListener('input', (e) => {
        const name = (e.target as HTMLElement).dataset.field;
        if (name && (e.target as HTMLElement).hasAttribute('aria-invalid')) check(name);
    });

    // Coming back to this page: the answers are still here.
    const draft = readDraft();
    if (draft) {
        input('from').value = draft.from.address;
        input('to').value = draft.to.address;
        input('date').value = draft.move_date;
        input('type').value = draft.move_type_id;
        stops.from = draft.from;
        stops.to = draft.to;
    }

    if (onMap(stops.from) || onMap(stops.to)) redraw();
    else map?.play();

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const invalid = Object.keys(rules).filter((name) => !check(name));

        if (invalid.length) {
            input(invalid[0]).focus();

            return;
        }

        const stop = (side: 'from' | 'to'): DraftStop => stops[side] ?? { address: input(side).value.trim() };

        saveDraft({ from: stop('from'), to: stop('to'), move_date: input('date').value, move_type_id: input('type').value });
        track('quote_start', { form_id: 'hero', page_path: location.pathname });
        location.assign(form.action);
    });
}
