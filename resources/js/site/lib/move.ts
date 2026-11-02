import type { AddressDetails } from './address';
import type { Rule } from './form';

/** Rules shared by the hero's first step and the full quote form, and the answers carried between them. */

export const addressRule =
    (question: string): Rule =>
    (v) =>
        !v ? question : v.length < 5 ? 'Please enter a full address, city or ZIP code.' : null;

export const moveDateRule =
    (date: HTMLInputElement | null): Rule =>
    (v) => {
        if (!v) return 'Please choose your moving date.';
        if (date?.min && v < date.min) return 'The moving date cannot be in the past.';
        if (date?.max && v > date.max) return 'Please choose a date within the next 18 months.';

        return null;
    };

export type DraftStop = Partial<AddressDetails> & { address: string };
export type QuoteDraft = { from: DraftStop; to: DraftStop; move_date: string; move_type_id: string };

/** sessionStorage, not the URL: addresses stay out of server logs, analytics and referrers. */
const KEY = 'quoteDraft';

function store(): Storage | null {
    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
}

export function saveDraft(draft: QuoteDraft): void {
    try {
        store()?.setItem(KEY, JSON.stringify(draft));
    } catch {
        // Storage full or blocked: the quote page simply shows every field.
    }
}

export function readDraft(): QuoteDraft | null {
    try {
        const draft = JSON.parse(store()?.getItem(KEY) ?? 'null');

        return draft?.from?.address !== undefined && draft?.to?.address !== undefined ? draft : null;
    } catch {
        return null;
    }
}

export function clearDraft(): void {
    try {
        store()?.removeItem(KEY);
    } catch {
        // Nothing to clear.
    }
}

/** Short name for a stop: the city when known, else the start of what was typed. */
export function stopName(stop: Partial<AddressDetails> & { address?: string }): string {
    return stop.city || stop.neighborhood || stop.county || (stop.address ?? '').split(',')[0];
}
