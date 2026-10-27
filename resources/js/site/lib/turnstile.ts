/**
 * Cloudflare Turnstile, loaded the first time a visitor uses a form (never on
 * page load). Most visitors see nothing ("interaction-only"); the widget only
 * appears when Cloudflare wants a click. The token is single-use, so it is
 * reset after every attempt.
 */

type TurnstileApi = {
    render: (el: HTMLElement, options: Record<string, unknown>) => string;
    getResponse: (id: string) => string | undefined;
    reset: (id: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let api: Promise<TurnstileApi> | null = null;
function load(): Promise<TurnstileApi> {
    api ??= new Promise<TurnstileApi>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT;
        script.async = true;
        script.onload = () => (window.turnstile ? resolve(window.turnstile) : reject(new Error('Turnstile missing')));
        script.onerror = () => {
            api = null; // allow a retry on the next attempt
            reject(new Error('Turnstile failed to load'));
        };
        document.head.append(script);
    });

    return api;
}

export type Challenge = {
    /** Resolves with a token, or null when none arrived in time. */
    token: (timeoutMs?: number) => Promise<string | null>;
    reset: () => void;
};

/** Attach Turnstile to a form that has a [data-turnstile] slot; null when the site has no keys. */
export function attachTurnstile(form: HTMLFormElement): Challenge | null {
    const slot = form.querySelector<HTMLElement>('[data-turnstile]');
    if (!slot) return null;

    let widget: Promise<string> | null = null;
    let waiting: ((token: string) => void)[] = [];

    const start = () =>
        (widget ??= load()
            .then((ts) =>
                ts.render(slot, {
                    sitekey: slot.dataset.sitekey,
                    action: slot.dataset.action,
                    appearance: 'interaction-only',
                    theme: 'light', // the forms sit on white cards
                    size: 'flexible',
                    'response-field-name': 'cf-turnstile-response',
                    // Lets the form give the widget room only while it is on screen.
                    'before-interactive-callback': () => (slot.dataset.visible = 'true'),
                    'after-interactive-callback': () => delete slot.dataset.visible,
                    callback: (token: string) => {
                        waiting.forEach((resolve) => resolve(token));
                        waiting = [];
                    },
                }),
            )
            .catch((error) => {
                widget = null;
                throw error;
            }));

    // The island itself may have been mounted by that first focus.
    if (form.contains(document.activeElement)) start().catch(() => {});
    else form.addEventListener('focusin', () => start().catch(() => {}), { once: true });

    return {
        async token(timeoutMs = 15000) {
            try {
                const id = await start();
                const ready = window.turnstile?.getResponse(id);
                if (ready) return ready;

                return await new Promise<string | null>((resolve) => {
                    waiting.push(resolve);
                    setTimeout(() => resolve(null), timeoutMs);
                });
            } catch {
                return null;
            }
        },
        reset() {
            widget?.then((id) => window.turnstile?.reset(id)).catch(() => {});
        },
    };
}
