import { track } from './analytics';
import { attachTurnstile } from './turnstile';

/**
 * Progressive enhancement for the server-rendered public forms: inline
 * validation, a fresh signed start time, Turnstile when enabled, AJAX submit
 * with server errors, and the GA4 / GTM dataLayer event on success. Without JS the form is a
 * normal POST, so nothing here is required for it to work.
 */

export type Rule = (value: string, form: HTMLFormElement) => string | null;

export type FormOptions = {
    rules: Record<string, Rule>;
    /** dataLayer event pushed on success, e.g. "generate_lead". */
    event: string;
    /** Called before validation (e.g. to normalise the phone field). */
    beforeSubmit?: (form: HTMLFormElement) => void;
    /** Called after the server accepts the form, before the redirect. */
    onSuccess?: () => void;
};

const field = (form: HTMLFormElement, name: string) =>
    form.elements.namedItem(name) as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null;

export function setError(form: HTMLFormElement, name: string, message: string | null): void {
    const input = field(form, name);
    const slot = form.querySelector<HTMLElement>(`[data-error-for="${name}"]`);

    if (input) {
        // A problem in the quote form's collapsed "Your move" fields: show them again.
        if (message) form.removeAttribute('data-draft');
        if (message) input.setAttribute('aria-invalid', 'true');
        else input.removeAttribute('aria-invalid');
    }

    if (slot) slot.textContent = message ?? '';
}

function setFormError(form: HTMLFormElement, message: string | null): void {
    const slot = form.querySelector<HTMLElement>('[data-form-error]');

    if (slot) slot.textContent = message ?? '';
}

/** Validate one field; returns true when it is valid. */
function check(form: HTMLFormElement, name: string, rules: Record<string, Rule>): boolean {
    const rule = rules[name];
    const input = field(form, name);

    if (!rule || !input) return true;

    const message = rule(input.value.trim(), form);
    setError(form, name, message);

    return message === null;
}

/** Replace the build-time start time with a fresh one (static pages can be weeks old). */
let tokenPromise: Promise<string | null> | null = null;
function freshToken(): Promise<string | null> {
    tokenPromise ??= fetch('/forms/token', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => data?.token ?? null)
        .catch(() => null);

    return tokenPromise;
}

export function enhanceForm(form: HTMLFormElement, options: FormOptions): void {
    if (form.dataset.enhanced) return;
    form.dataset.enhanced = 'true';

    const touched = new Set<string>();
    const button = form.querySelector<HTMLButtonElement>('button[type="submit"]');
    const label = button?.textContent?.trim() ?? '';
    const challenge = attachTurnstile(form);

    form.addEventListener(
        'focusin',
        () =>
            freshToken().then((token) => {
                const input = field(form, '_started');
                if (token && input) input.value = token;
            }),
        { once: true },
    );

    // Validate on leave; once a field was flagged, re-check while typing so the message clears.
    form.addEventListener('focusout', (e) => {
        const name = (e.target as HTMLInputElement).name;
        if (!name || !(name in options.rules)) return;
        touched.add(name);
        check(form, name, options.rules);
    });
    form.addEventListener('input', (e) => {
        const name = (e.target as HTMLInputElement).name;
        if (touched.has(name) && (e.target as HTMLElement).getAttribute('aria-invalid')) check(form, name, options.rules);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        setFormError(form, null);
        options.beforeSubmit?.(form);

        const invalid = Object.keys(options.rules).filter((name) => !check(form, name, options.rules));

        if (invalid.length) {
            field(form, invalid[0])?.focus();

            return;
        }

        if (button) {
            button.disabled = true;
            button.textContent = 'Sending…';
        }

        try {
            await freshToken();

            if (challenge && !(await challenge.token())) {
                setFormError(form, 'Please complete the security check below, then send the form again.');

                return;
            }

            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok && data.ok) {
                track(options.event, { form_id: form.id, page_path: location.pathname });
                options.onSuccess?.();
                location.assign(data.redirect || '/thank-you');

                return;
            }

            if (response.status === 422 && data.errors) {
                const names = Object.keys(data.errors);
                names.forEach((name) => (name === 'form' ? setFormError(form, data.errors[name][0]) : setError(form, name, data.errors[name][0])));
                field(form, names.find((n) => n !== 'form') ?? '')?.focus();
            } else {
                setFormError(form, data.message || 'Something went wrong. Please try again or call us.');
            }

            challenge?.reset(); // tokens are single-use
        } catch {
            setFormError(form, 'We could not send the form. Check your connection and try again, or call us.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = label;
            }
        }
    });
}

// ---- Shared rules ---------------------------------------------------------------

export const required =
    (message: string): Rule =>
    (v) =>
        v ? null : message;

export const email: Rule = (v) => (!v ? 'Please enter your email address.' : /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? null : 'Please enter a valid email address.');

/** US number: 10 digits (optionally +1), area code and exchange start with 2–9. */
export const usPhone =
    (optional = false): Rule =>
    (v) => {
        if (!v) return optional ? null : 'Please enter your phone number.';

        let digits = v.replace(/\D/g, '');
        if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);

        return /^[2-9]\d{2}[2-9]\d{6}$/.test(digits) ? null : 'Please enter a valid US phone number, e.g. (214) 555-0100.';
    };

/** "2145550100" → "(214) 555-0100" while typing. */
export function maskPhone(input: HTMLInputElement): void {
    input.addEventListener('input', () => {
        let digits = input.value.replace(/\D/g, '');
        if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);
        digits = digits.slice(0, 10);

        const formatted =
            digits.length > 6 ? `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}` : digits.length > 3 ? `(${digits.slice(0, 3)}) ${digits.slice(3)}` : digits;

        // Leave deletions alone so backspace over ")" or "-" works.
        if (formatted.length >= input.value.length) input.value = formatted;
    });
}
