import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Module not built yet: shown greyed out with a "Soon" badge. */
    disabled?: boolean;
    /** Only highlight on an exact URL match (e.g. the dashboard at /admin). */
    exact?: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
