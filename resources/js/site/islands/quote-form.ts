import { enhanceForm, email, maskPhone, required, usPhone } from '../lib/form';
import type { Rule } from '../lib/form';
import { attachPlaces } from '../lib/places';

/** Quote form island: validation, phone mask, Places autocomplete and AJAX submit. */
export default function mount(form: HTMLFormElement): void {
    const hidden = (side: string, part: string) => form.elements.namedItem(`${side}_${part}`) as HTMLInputElement | null;
    const key = form.dataset.placesKey ?? '';

    for (const side of ['from', 'to']) {
        const input = form.elements.namedItem(`${side}_address`) as HTMLInputElement | null;
        if (!input) continue;

        attachPlaces(
            input,
            key,
            (place) => {
                (['city', 'state', 'zip', 'lat', 'lng'] as const).forEach((part) => {
                    const el = hidden(side, part);
                    if (el) el.value = place[part];
                });
            },
            // Typing after a selection makes the stored city/ZIP/coordinates stale.
            () => ['city', 'state', 'zip', 'lat', 'lng'].forEach((part) => hidden(side, part)?.value && (hidden(side, part)!.value = '')),
        );
    }

    const phone = form.elements.namedItem('phone') as HTMLInputElement | null;
    if (phone) maskPhone(phone);

    const date = form.elements.namedItem('move_date') as HTMLInputElement | null;
    const dateRule: Rule = (v) => {
        if (!v) return 'Please choose your moving date.';
        if (date?.min && v < date.min) return 'The moving date cannot be in the past.';
        if (date?.max && v > date.max) return 'Please choose a date within the next 18 months.';

        return null;
    };

    const address = (question: string): Rule => (v) => (!v ? question : v.length < 5 ? 'Please enter a full address, city or ZIP code.' : null);

    enhanceForm(form, {
        event: 'generate_lead',
        rules: {
            full_name: (v) => (v.length >= 2 ? null : 'Please enter your full name.'),
            email,
            phone: usPhone(),
            from_address: address('Where are you moving from?'),
            to_address: address('Where are you moving to?'),
            move_date: dateRule,
            move_type_id: required('Please choose the type of move.'),
        },
    });
}
