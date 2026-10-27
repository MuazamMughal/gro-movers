/**
 * Opens a preview of unsaved editor data in a new tab by POSTing it as JSON
 * (too large and too private for a URL). The server renders without saving.
 */
export function openPreview(url: string, payload: unknown): void {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.target = '_blank';
    form.style.display = 'none';

    // Plain form posts carry the session CSRF token (from the admin layout's meta tag).
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    const fields: Record<string, string> = {
        payload: JSON.stringify(payload),
        ...(token ? { _token: token } : {}),
    };

    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
    form.remove();
}
