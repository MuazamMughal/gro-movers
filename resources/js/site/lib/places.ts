/**
 * Address autocomplete with Google Places API (New): AutocompleteSuggestion +
 * session tokens, US only. The Maps script loads on the first focus of an
 * address field, never on page load. Any failure (no key, quota, blocked
 * script) leaves a plain text input that still submits.
 *
 * Custom listbox (ARIA combobox): arrow keys, Enter, Escape, mouse/touch.
 */

/* Minimal shapes of the Places (New) objects we use. */
type PlacePrediction = { text: { toString(): string }; toPlace(): Place };
type Suggestion = { placePrediction: PlacePrediction | null };
type AddressComponent = { types: string[]; shortText: string | null; longText: string | null };
type Place = {
    formattedAddress?: string;
    addressComponents?: AddressComponent[];
    location?: { lat(): number; lng(): number };
    fetchFields(options: { fields: string[] }): Promise<unknown>;
};
type PlacesLibrary = {
    AutocompleteSuggestion: { fetchAutocompleteSuggestions(request: Record<string, unknown>): Promise<{ suggestions: Suggestion[] }> };
    AutocompleteSessionToken: new () => unknown;
};

export type PlaceDetails = { address: string; city: string; state: string; zip: string; lat: string; lng: string };

declare global {
    interface Window {
        google?: { maps?: { importLibrary(name: string): Promise<unknown> } };
        __placesReady?: () => void;
    }
}

let libraryPromise: Promise<PlacesLibrary> | null = null;

function loadPlaces(key: string): Promise<PlacesLibrary> {
    libraryPromise ??= new Promise<void>((resolve, reject) => {
        if (window.google?.maps?.importLibrary) return resolve();

        window.__placesReady = () => resolve();
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&loading=async&callback=__placesReady`;
        script.async = true;
        script.onerror = () => reject(new Error('Google Maps failed to load'));
        document.head.appendChild(script);
    }).then(() => window.google!.maps!.importLibrary('places') as Promise<PlacesLibrary>);

    return libraryPromise;
}

function details(place: Place, fallback: string): PlaceDetails {
    const part = (type: string, short = false) => {
        const c = place.addressComponents?.find((a) => a.types.includes(type));

        return (short ? c?.shortText : c?.longText) ?? '';
    };

    return {
        address: place.formattedAddress?.replace(/, USA$/, '') || fallback,
        city: part('locality') || part('sublocality') || part('postal_town'),
        state: part('administrative_area_level_1', true),
        zip: part('postal_code'),
        lat: place.location ? place.location.lat().toFixed(7) : '',
        lng: place.location ? place.location.lng().toFixed(7) : '',
    };
}

let uid = 0;

/**
 * Turn an address input into a combobox. `onSelect` receives the parsed place;
 * `onEdit` runs when the visitor types again after choosing (hidden fields go stale).
 */
export function attachPlaces(input: HTMLInputElement, key: string, onSelect: (place: PlaceDetails) => void, onEdit: () => void): void {
    if (!key) return;

    const listId = `places-list-${++uid}`;
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

    let library: PlacesLibrary | null = null;
    let session: unknown = null;
    let suggestions: Suggestion[] = [];
    let active = -1;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let request = 0;
    let failed = false;

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
                li.textContent = s.placePrediction!.text.toString().replace(/, USA$/, '');
                // mousedown keeps focus in the input (click would blur it first).
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    void choose(i);
                });

                return li;
            }),
        );
        list.hidden = suggestions.length === 0;
        input.setAttribute('aria-expanded', String(!list.hidden));
        active = -1;
    };

    const choose = async (index: number) => {
        const prediction = suggestions[index]?.placePrediction;
        if (!prediction) return;

        const text = prediction.text.toString().replace(/, USA$/, '');
        input.value = text;
        close();

        try {
            const place = prediction.toPlace();
            await place.fetchFields({ fields: ['formattedAddress', 'addressComponents', 'location'] });
            const parsed = details(place, text);
            input.value = parsed.address;
            onSelect(parsed);
        } catch {
            onSelect({ address: text, city: '', state: '', zip: '', lat: '', lng: '' });
        }

        // A session ends with a selection; the next search starts a new one (billing).
        session = library ? new library.AutocompleteSessionToken() : null;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const search = async () => {
        const value = input.value.trim();
        const current = ++request;

        if (value.length < 3 || failed) return close();

        try {
            library ??= await loadPlaces(key);
            session ??= new library.AutocompleteSessionToken();
            const result = await library.AutocompleteSuggestion.fetchAutocompleteSuggestions({ input: value, sessionToken: session, includedRegionCodes: ['us'] });

            if (current !== request) return; // a newer keystroke won

            suggestions = result.suggestions.filter((s) => s.placePrediction).slice(0, 5);
            render();
        } catch {
            failed = true; // plain text from now on
            close();
        }
    };

    input.addEventListener('focus', () => void loadPlaces(key).catch(() => (failed = true)), { once: true });

    input.addEventListener('input', () => {
        onEdit();
        clearTimeout(timer);
        timer = setTimeout(search, 250);
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
            void choose(active);
        } else if (e.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => setTimeout(close, 150));
}
