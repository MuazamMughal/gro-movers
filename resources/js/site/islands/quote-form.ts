import { enhanceForm, email, maskPhone, required, usPhone } from '../lib/form';
import type { Rule } from '../lib/form';
import { ADDRESS_PARTS, attachAddressSearch } from '../lib/address';
import { addressRule, clearDraft, moveDateRule, readDraft } from '../lib/move';

/** Quote form island: validation, phone mask, Photon address autocomplete and AJAX submit. */
export default function mount(form: HTMLFormElement): void {
    const hidden = (side: string, part: string) => form.elements.namedItem(`${side}_${part}`) as HTMLInputElement | null;
    const server = form.dataset.addressApi ?? '';

    for (const side of ['from', 'to']) {
        const input = form.elements.namedItem(`${side}_address`) as HTMLInputElement | null;
        if (!input) continue;

        attachAddressSearch(
            input,
            server,
            (address) => {
                ADDRESS_PARTS.forEach((part) => {
                    const el = hidden(side, part);
                    if (el) el.value = address[part];
                });
            },
            // Typing after a selection makes the stored street/city/ZIP/coordinates stale.
            () => ADDRESS_PARTS.forEach((part) => hidden(side, part)?.value && (hidden(side, part)!.value = '')),
        );
    }

    const phone = form.elements.namedItem('phone') as HTMLInputElement | null;
    if (phone) maskPhone(phone);

    const date = form.elements.namedItem('move_date') as HTMLInputElement | null;
    const dateRule = moveDateRule(date);

    applyDraft(form, dateRule);

    enhanceForm(form, {
        event: 'generate_lead',
        rules: {
            full_name: (v) => (v.length >= 2 ? null : 'Please enter your full name.'),
            email,
            phone: usPhone(),
            from_address: addressRule('Where are you moving from?'),
            to_address: addressRule('Where are you moving to?'),
            move_date: dateRule,
            move_type_id: required('Please choose the type of move.'),
        },
        onSuccess: clearDraft,
    });
}

/**
 * Answers from the hero's first step: fill the fields and show them as a short
 * "Your move" summary, so only the contact details are left to type. If anything
 * looks wrong (an old date, a removed move type) every field stays open.
 */
function applyDraft(form: HTMLFormElement, dateRule: Rule): void {
    const draft = readDraft();
    const named = (name: string) => form.elements.namedItem(name) as HTMLInputElement | HTMLSelectElement | null;
    const open = () => form.removeAttribute('data-draft');

    if (!draft) return open();

    for (const side of ['from', 'to'] as const) {
        const stop = draft[side];
        const address = named(`${side}_address`);
        if (address) address.value = stop.address;
        ADDRESS_PARTS.forEach((part) => {
            const el = named(`${side}_${part}`);
            if (el) el.value = stop[part] ?? '';
        });
    }

    const date = named('move_date');
    const type = named('move_type_id') as HTMLSelectElement | null;
    if (date) date.value = draft.move_date;
    if (type) type.value = draft.move_type_id;

    const typeName = type?.selectedOptions[0]?.textContent?.trim();

    if (dateRule(draft.move_date, form) || !type?.value || draft.from.address.length < 5 || draft.to.address.length < 5) return open();

    const [year, month, day] = draft.move_date.split('-').map(Number);
    const text: Record<string, string> = {
        from: draft.from.address,
        to: draft.to.address,
        when: new Date(year, month - 1, day).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }),
        type: typeName ?? '',
    };

    form.querySelectorAll<HTMLElement>('[data-summary]').forEach((el) => (el.textContent = text[el.dataset.summary!] ?? ''));
    form.setAttribute('data-draft', '1');
    form.querySelector<HTMLButtonElement>('[data-move-edit]')?.addEventListener('click', () => {
        open();
        (named('from_address') as HTMLInputElement | null)?.focus();
    });
}
