<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CircleHelp,
    ExternalLink,
    FileText,
    FolderOpen,
    Grid3x3,
    Image,
    Inbox,
    LayoutGrid,
    ListChecks,
    Mail,
    MapPin,
    Menu,
    Newspaper,
    Search,
    Settings,
    ShieldCheck,
    Star,
    Tags,
    Truck,
    UserRound,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { index as areaServicesIndex } from '@/routes/admin/area-services';
import { index as areasIndex } from '@/routes/admin/areas';
import { index as authorsIndex } from '@/routes/admin/authors';
import { index as blogCategoriesIndex } from '@/routes/admin/blog-categories';
import { index as faqsIndex } from '@/routes/admin/faqs';
import { index as leadsIndex } from '@/routes/admin/leads';
import { index as mediaIndex } from '@/routes/admin/media';
import { index as menusIndex } from '@/routes/admin/menus';
import { index as messagesIndex } from '@/routes/admin/messages';
import { index as moveTypesIndex } from '@/routes/admin/move-types';
import { index as pagesIndex } from '@/routes/admin/pages';
import { index as postsIndex } from '@/routes/admin/posts';
import { index as reviewsIndex } from '@/routes/admin/reviews';
import { index as servicesIndex } from '@/routes/admin/services';
import { report as seoReport } from '@/routes/admin/seo';
import { index as settingsIndex } from '@/routes/admin/settings';
import { index as tagsIndex } from '@/routes/admin/tags';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavGroup, NavItem } from '@/types';
import { computed } from 'vue';
import { useAccess } from '@/composables/useAccess';

// Modules marked `disabled` are built in later tasks (see docs/02-build-prompt.md §14).
const page = usePage();
const inbox = computed(() => page.props.inbox as { leads: number | null; messages: number | null } | null);
const { can, isAdministrator } = useAccess();

const allGroups = computed<NavGroup[]>(() => [
    {
        label: 'Overview',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid, exact: true },
        ],
    },
    {
        label: 'Content',
        items: [
            { title: 'Pages', href: pagesIndex(), icon: FileText, permission: 'pages' },
            { title: 'Services', href: servicesIndex(), icon: Truck, permission: 'services' },
            { title: 'Service Areas', href: areasIndex(), icon: MapPin, permission: 'areas' },
            { title: 'Area × Service', href: areaServicesIndex(), icon: Grid3x3, permission: 'services' },
            { title: 'Reviews', href: reviewsIndex(), icon: Star, permission: 'reviews' },
            { title: 'FAQs', href: faqsIndex(), icon: CircleHelp, permission: 'faqs' },
            { title: 'Media Library', href: mediaIndex(), icon: Image, permission: 'media' },
        ],
    },
    {
        label: 'Blog',
        items: [
            { title: 'Posts', href: postsIndex(), icon: Newspaper, permission: 'blog' },
            { title: 'Categories', href: blogCategoriesIndex(), icon: FolderOpen, permission: 'blog' },
            { title: 'Authors', href: authorsIndex(), icon: UserRound, permission: 'blog' },
            { title: 'Tags', href: tagsIndex(), icon: Tags, permission: 'blog' },
        ],
    },
    {
        label: 'Leads',
        items: [
            { title: 'Quote Requests', href: leadsIndex(), icon: Inbox, badge: inbox.value?.leads ?? undefined, permission: 'leads.view' },
            { title: 'Contact Messages', href: messagesIndex(), icon: Mail, badge: inbox.value?.messages ?? undefined, permission: 'messages' },
        ],
    },
    {
        label: 'Site',
        items: [
            { title: 'Menus', href: menusIndex(), icon: Menu, permission: 'menus' },
            { title: 'Move Types', href: moveTypesIndex(), icon: ListChecks, permission: 'move_types' },
            { title: 'SEO Tools', href: seoReport(), icon: Search, permission: 'seo' },
            { title: 'Settings', href: settingsIndex(), icon: Settings, permission: 'settings' },
            { title: 'Users', href: usersIndex(), icon: Users, permission: 'administrator' },
            { title: 'Roles', href: rolesIndex(), icon: ShieldCheck, permission: 'administrator' },
        ],
    },
]);

// Only the modules the user's role allows; groups left empty are dropped.
const navGroups = computed<NavGroup[]>(() =>
    allGroups.value
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.permission || (item.permission === 'administrator' ? isAdministrator.value : can(item.permission))),
        }))
        .filter((group) => group.items.length),
);

const footerNavItems: NavItem[] = [
    { title: 'View website', href: home(), icon: ExternalLink },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="h-auto p-1 hover:bg-transparent active:bg-transparent" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                v-for="group in navGroups"
                :key="group.label"
                :label="group.label"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
