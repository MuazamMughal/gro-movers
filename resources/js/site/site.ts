/**
 * Public site script (tiny, deferred module). It:
 * - remembers where the visitor came from (landing page, referrer, UTM tags) for the forms,
 * - loads form islands only when a form comes near the screen or is focused,
 * - pushes the GA4 event on /thank-you after a no-JS form submit (?sent=…).
 */

const islands: Record<string, () => Promise<{ default: (el: HTMLFormElement) => void }>> = {
    'quote-form': () => import('./islands/quote-form'),
    'contact-form': () => import('./islands/contact-form'),
};

const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'msclkid'];

function storage(): Storage | null {
    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
}

/** First landing of the visit: its URL, the external referrer and any campaign tags. */
function rememberVisit(): { landing: string; referrer: string; utm: Record<string, string> } {
    const store = storage();
    const saved = JSON.parse(store?.getItem('visit') ?? 'null');
    const params = new URLSearchParams(location.search);
    const utm = Object.fromEntries(UTM_KEYS.filter((k) => params.get(k)).map((k) => [k, params.get(k)!.slice(0, 200)]));
    const external = document.referrer && new URL(document.referrer).host !== location.host ? document.referrer : '';

    const visit = saved ?? { landing: location.href, referrer: external, utm: {} };

    // A new campaign click during the visit replaces the old tags.
    if (Object.keys(utm).length) visit.utm = utm;

    store?.setItem('visit', JSON.stringify(visit));

    return visit;
}

function fillTracking(form: HTMLFormElement, visit: ReturnType<typeof rememberVisit>): void {
    const set = (name: string, value: string) => {
        const input = form.elements.namedItem(name) as HTMLInputElement | null;
        if (input && !input.value) input.value = value;
    };

    set('source_url', location.href);
    set('referrer', visit.referrer || (visit.landing !== location.href ? `landing: ${visit.landing}` : ''));
    set('utm', Object.keys(visit.utm).length ? JSON.stringify(visit.utm) : '');
}

function mountIslands(): void {
    const forms = document.querySelectorAll<HTMLFormElement>('form[data-island]');
    if (!forms.length) return;

    const visit = rememberVisit();
    const mount = (form: HTMLFormElement) => {
        if (form.dataset.mounted) return;
        form.dataset.mounted = 'true';
        islands[form.dataset.island!]?.().then((m) => m.default(form));
    };

    const observer = 'IntersectionObserver' in window
        ? new IntersectionObserver((entries) => entries.forEach((e) => e.isIntersecting && (observer!.unobserve(e.target), mount(e.target as HTMLFormElement))), { rootMargin: '300px' })
        : null;

    forms.forEach((form) => {
        fillTracking(form, visit);
        form.addEventListener('focusin', () => mount(form), { once: true });
        observer ? observer.observe(form) : mount(form);
    });
}

/** GA4 / GTM event for forms sent without JS: the server redirects to /thank-you?sent=quote|contact. */
function thankYouEvent(): void {
    const params = new URLSearchParams(location.search);
    const sent = params.get('sent');
    if (!sent || !['quote', 'contact'].includes(sent)) return;

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: sent === 'quote' ? 'generate_lead' : 'contact_submit', form_id: `${sent}-nojs`, page_path: location.pathname });

    // Drop the parameter so a reload does not count the lead twice.
    params.delete('sent');
    history.replaceState(null, '', location.pathname + (params.size ? `?${params}` : '') + location.hash);
}

rememberVisit();
mountIslands();
thankYouEvent();
