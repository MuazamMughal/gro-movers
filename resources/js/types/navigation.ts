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
    /** Permission needed to see the item ('administrator' for users and roles); see useAccess(). */
    permission?: string;
    /** Count shown on the right (e.g. unread leads); hidden when 0. */
    badge?: number;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
