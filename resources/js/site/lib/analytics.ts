/**
 * Analytics without slowing the page: GTM, GA4 and the Facebook Pixel load on
 * the first interaction or when the browser is idle (IDs come from
 * window.siteAnalytics, printed by the layout). Events are pushed to the
 * dataLayer right away and are sent once the tags load.
 *
 * Events: generate_lead, contact_submit, click_to_call {location}, quote_cta_click {location}.
 */

type Config = { gtm?: string; ga4?: string; pixel?: string };

declare global {
    interface Window {
        dataLayer?: unknown[];
        siteAnalytics?: Config;
        gtag?: (...args: unknown[]) => void;
        fbq?: ((...args: unknown[]) => void) & { queue?: unknown[]; loaded?: boolean; version?: string; push?: unknown; callMethod?: (...args: unknown[]) => void };
        _fbq?: unknown;
    }
}

const config = (): Config => window.siteAnalytics ?? {};

export function track(event: string, params: Record<string, unknown> = {}): void {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event, ...params });

    // GA4 without GTM reads gtag() commands, not plain dataLayer objects.
    if (config().ga4 && window.gtag) window.gtag('event', event, params);

    if (event === 'generate_lead' && window.fbq) window.fbq('track', 'Lead');
    if (event === 'contact_submit' && window.fbq) window.fbq('track', 'Contact');
}

function script(src: string): void {
    const s = document.createElement('script');
    s.src = src;
    s.async = true;
    document.head.appendChild(s);
}

let loaded = false;

function load(): void {
    if (loaded) return;
    loaded = true;

    const { gtm, ga4, pixel } = config();
    window.dataLayer = window.dataLayer || [];

    if (gtm) {
        window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
        script(`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtm)}`);
    } else if (ga4) {
        window.gtag = function () {
            // eslint-disable-next-line prefer-rest-params
            window.dataLayer!.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', ga4);
        script(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4)}`);
    }

    if (pixel) {
        const fbq: NonNullable<Window['fbq']> = function (...args: unknown[]) {
            fbq.callMethod ? fbq.callMethod(...args) : fbq.queue!.push(args);
        };
        fbq.queue = [];
        fbq.loaded = true;
        fbq.version = '2.0';
        fbq.push = fbq;
        window.fbq = window._fbq = fbq;
        script('https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', pixel);
        fbq('track', 'PageView');
    }
}

/** Load tags on the first interaction, or after the page settles (idle, max ~4 s). */
export function initAnalytics(): void {
    const { gtm, ga4, pixel } = config();
    if (!gtm && !ga4 && !pixel) return;

    const events = ['pointerdown', 'keydown', 'scroll', 'touchstart'] as const;
    const once = () => {
        events.forEach((e) => window.removeEventListener(e, once));
        load();
    };
    events.forEach((e) => window.addEventListener(e, once, { passive: true, once: true }));

    if ('requestIdleCallback' in window) window.requestIdleCallback(once, { timeout: 4000 });
    else setTimeout(once, 3000);
}

/** Where on the page a click happened: header, sticky_bar, footer, hero, cta or content. */
function location(el: Element): string {
    const explicit = el.closest<HTMLElement>('[data-call-location], [data-cta-location]');
    if (explicit) return explicit.dataset.callLocation || explicit.dataset.ctaLocation || 'content';
    if (el.closest('header')) return 'header';
    if (el.closest('nav[aria-label="Call or get a quote"]')) return 'sticky_bar';
    if (el.closest('footer')) return 'footer';

    return 'content';
}

/** click_to_call for every tel: link, quote_cta_click for links to the quote page. */
export function trackClicks(): void {
    document.addEventListener('click', (e) => {
        const link = (e.target as Element | null)?.closest?.('a[href]');
        if (!link) return;

        const href = link.getAttribute('href') ?? '';

        if (href.startsWith('tel:')) {
            track('click_to_call', { location: location(link), link_url: href });
        } else if (/^(https?:\/\/[^/]+)?\/get-a-quote\/?(\?|#|$)/.test(href)) {
            track('quote_cta_click', { location: location(link), link_text: link.textContent?.trim().slice(0, 60) });
        }
    });
}
