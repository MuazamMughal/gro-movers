export type LinkType = 'page' | 'service' | 'area' | 'post' | 'category' | 'url';
export type RecordType = Exclude<LinkType, 'url'>;
export type Target = { id: number; name: string; path: string | null; live: boolean };
export type Targets = Record<RecordType, Target[]>;

export type MenuItem = {
    _key: string;
    label: string;
    link_type: LinkType;
    linkable_id: number | null;
    url: string | null;
    auto_children: 'services' | 'areas' | null;
    target_blank: boolean;
    broken?: boolean;
    children: MenuItem[];
};

export const linkTypes: { value: LinkType; label: string }[] = [
    { value: 'page', label: 'Page' },
    { value: 'service', label: 'Service' },
    { value: 'area', label: 'Service area' },
    { value: 'category', label: 'Blog category' },
    { value: 'post', label: 'Blog post' },
    { value: 'url', label: 'Custom link' },
];

export const typeLabel = (type: LinkType) => linkTypes.find((t) => t.value === type)?.label ?? type;

export const targetsFor = (targets: Targets, type: LinkType): Target[] => (type === 'url' ? [] : targets[type]);

export const targetOf = (targets: Targets, item: MenuItem): Target | undefined =>
    targetsFor(targets, item.link_type).find((t) => t.id === item.linkable_id);

export function summary(targets: Targets, item: MenuItem): string {
    if (item.link_type === 'url') return item.url || 'No link yet';

    const t = targetOf(targets, item);

    return t ? `${typeLabel(item.link_type)}: ${t.name}` : 'Not linked yet';
}

/** Why the item is hidden on the website, if it is. */
export function warning(targets: Targets, item: MenuItem): string | null {
    if (item.broken) return 'The linked item was deleted or is hidden. This menu item is not shown on the website.';

    const t = targetOf(targets, item);

    return t && !t.live ? 'The linked item is not published or not active yet, so this menu item is hidden on the website.' : null;
}
