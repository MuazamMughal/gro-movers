/**
 * Address autocomplete with Photon, the open-source OpenStreetMap geocoder
 * (https://github.com/komoot/photon), US only. No key and no script to load:
 * one fetch per pause in typing. Any failure (no server URL, server down,
 * rate limited) leaves a plain text input that still submits.
 *
 * Custom listbox (ARIA combobox): arrow keys, Enter, Escape, mouse/touch.
 */

/** The Photon feature properties we read (GeoJSON `properties`). */
type PhotonProperties = {
    osm_type?: 'N' | 'W' | 'R';
    osm_id?: number;
    osm_value?: string;
    type?: string;
    name?: string;
    housenumber?: string;
    street?: string;
    locality?: string;
    district?: string;
    city?: string;
    county?: string;
    state?: string;
    postcode?: string;
    country?: string;
    countrycode?: string;
};
type PhotonFeature = { properties: PhotonProperties; geometry: { coordinates: [number, number] } };

/** Every part we store; the keys match the hidden `{side}_{part}` inputs. */
export type AddressDetails = {
    address: string;
    house_number: string;
    street: string;
    neighborhood: string;
    city: string;
    county: string;
    state: string;
    state_name: string;
    zip: string;
    country: string;
    country_code: string;
    lat: string;
    lng: string;
    osm_id: string;
};

export const ADDRESS_PARTS = ['house_number', 'street', 'neighborhood', 'city', 'county', 'state', 'state_name', 'zip', 'country', 'country_code', 'lat', 'lng', 'osm_id'] as const;

/** Photon returns full state names; leads store the USPS code too. */
const STATES: Record<string, string> = {
    Alabama: 'AL', Alaska: 'AK', Arizona: 'AZ', Arkansas: 'AR', California: 'CA', Colorado: 'CO', Connecticut: 'CT', Delaware: 'DE',
    'District of Columbia': 'DC', Florida: 'FL', Georgia: 'GA', Hawaii: 'HI', Idaho: 'ID', Illinois: 'IL', Indiana: 'IN', Iowa: 'IA',
    Kansas: 'KS', Kentucky: 'KY', Louisiana: 'LA', Maine: 'ME', Maryland: 'MD', Massachusetts: 'MA', Michigan: 'MI', Minnesota: 'MN',
    Mississippi: 'MS', Missouri: 'MO', Montana: 'MT', Nebraska: 'NE', Nevada: 'NV', 'New Hampshire': 'NH', 'New Jersey': 'NJ',
    'New Mexico': 'NM', 'New York': 'NY', 'North Carolina': 'NC', 'North Dakota': 'ND', Ohio: 'OH', Oklahoma: 'OK', Oregon: 'OR',
    Pennsylvania: 'PA', 'Rhode Island': 'RI', 'South Carolina': 'SC', 'South Dakota': 'SD', Tennessee: 'TN', Texas: 'TX', Utah: 'UT',
    Vermont: 'VT', Virginia: 'VA', Washington: 'WA', 'West Virginia': 'WV', Wisconsin: 'WI', Wyoming: 'WY', 'Puerto Rico': 'PR',
    Guam: 'GU', 'United States Virgin Islands': 'VI', 'American Samoa': 'AS', 'Northern Mariana Islands': 'MP',
};

/** Photon layers worth moving to or from (not counties, states or countries). */
const LAYERS = ['house', 'street', 'locality', 'district', 'city', 'other'];

function details(p: PhotonProperties, [lng, lat]: [number, number]): AddressDetails {
    const isPostcode = p.osm_value === 'postcode';
    const isPlace = ['city', 'district', 'locality'].includes(p.type ?? '');
    const city = p.city || (p.type === 'city' ? p.name : '') || '';
    const street = p.street || (p.type === 'street' ? p.name : '') || '';
    const zip = (isPostcode ? p.name : p.postcode) ?? '';
    const state = STATES[p.state ?? ''] ?? '';

    // "1200 Main Street, Dallas, TX 75202"; a building or business name leads when there is no street.
    const line1 = street ? [p.housenumber, street].filter(Boolean).join(' ') : !isPostcode && !isPlace && p.name !== city ? (p.name ?? '') : '';
    const place = isPlace && p.type !== 'city' ? p.name : '';
    const address = [line1, place, city, [state || p.state, zip].filter(Boolean).join(' ')].filter(Boolean).join(', ');

    return {
        address,
        house_number: p.housenumber ?? '',
        street,
        neighborhood: p.locality || p.district || place || '',
        city,
        county: p.county ?? '',
        state,
        state_name: p.state ?? '',
        // OSM postcodes are free text ("75201;75202"); keep only a clean ZIP.
        zip: /^\d{5}(-\d{4})?$/.test(zip) ? zip : '',
        country: p.country ?? '',
        country_code: (p.countrycode ?? '').toUpperCase(),
        lat: lat.toFixed(7),
        lng: lng.toFixed(7),
        osm_id: p.osm_type && p.osm_id ? `${p.osm_type}${p.osm_id}` : '',
    };
}

async function fetchSuggestions(server: string, query: string, signal: AbortSignal): Promise<AddressDetails[]> {
    const params = new URLSearchParams({ q: query, limit: '8', lang: 'en', countrycode: 'us' });
    LAYERS.forEach((layer) => params.append('layer', layer));

    const response = await fetch(`${server.replace(/\/+$/, '')}/api/?${params}`, { signal });
    if (!response.ok) throw new Error(`Photon ${response.status}`);

    const { features } = (await response.json()) as { features: PhotonFeature[] };
    const seen = new Set<string>();

    // A shop and the building it sits in share one address: list it once.
    return features
        .filter((f) => f.properties.countrycode === 'US')
        .map((f) => details(f.properties, f.geometry.coordinates))
        .filter((d) => d.address && !seen.has(d.address) && seen.add(d.address))
        .slice(0, 5);
}

let uid = 0;

/**
 * Turn an address input into a combobox. `onSelect` receives the parsed address;
 * `onEdit` runs when the visitor types again after choosing (hidden fields go stale).
 */
export function attachAddressSearch(input: HTMLInputElement, server: string, onSelect: (address: AddressDetails) => void, onEdit: () => void): void {
    if (!server) return;

    const listId = `address-list-${++uid}`;
    const list = document.createElement('ul');
    list.id = listId;
    list.setAttribute('role', 'listbox');
    list.className = 'absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-lg border border-border bg-white py-1 text-left text-secondary shadow-xl';
    list.hidden = true;
    input.parentElement!.classList.add('relative');
    input.insertAdjacentElement('afterend', list);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);

    let suggestions: AddressDetails[] = [];
    let active = -1;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let pending: AbortController | null = null;
    let failures = 0;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };

    const highlight = (index: number) => {
        active = index;
        [...list.children].forEach((li, i) => {
            li.setAttribute('aria-selected', String(i === index));
            li.classList.toggle('bg-muted', i === index);
        });

        if (index >= 0) input.setAttribute('aria-activedescendant', `${listId}-${index}`);
        else input.removeAttribute('aria-activedescendant');
    };

    const render = () => {
        list.replaceChildren(
            ...suggestions.map((s, i) => {
                const li = document.createElement('li');
                li.id = `${listId}-${i}`;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                li.className = 'cursor-pointer px-4 py-2.5 text-sm hover:bg-muted';
                li.textContent = s.address;
                // mousedown keeps focus in the input (click would blur it first).
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    choose(i);
                });

                return li;
            }),
        );
        list.hidden = suggestions.length === 0;
        input.setAttribute('aria-expanded', String(!list.hidden));
        active = -1;
    };

    const choose = (index: number) => {
        const chosen = suggestions[index];
        if (!chosen) return;

        input.value = chosen.address;
        close();
        onSelect(chosen);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const search = async () => {
        const value = input.value.trim();
        pending?.abort();

        // Three failures in a row: the server is down or rate limiting us, stay plain text.
        if (value.length < 3 || failures >= 3) return close();

        pending = new AbortController();

        try {
            suggestions = await fetchSuggestions(server, value, pending.signal);
            failures = 0;
            render();
        } catch (e) {
            if ((e as Error).name === 'AbortError') return; // a newer keystroke won
            failures++;
            close();
        }
    };

    input.addEventListener('input', () => {
        onEdit();
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    });

    input.addEventListener('keydown', (e) => {
        if (list.hidden) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlight((active + 1) % suggestions.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlight(active <= 0 ? suggestions.length - 1 : active - 1);
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            choose(active);
        } else if (e.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => setTimeout(close, 150));
}
