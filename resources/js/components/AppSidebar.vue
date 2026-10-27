<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
import { index as mediaIndex } from '@/routes/admin/media';
import { index as moveTypesIndex } from '@/routes/admin/move-types';
import { index as pagesIndex } from '@/routes/admin/pages';
import { index as postsIndex } from '@/routes/admin/posts';
import { index as servicesIndex } from '@/routes/admin/services';
import { index as settingsIndex } from '@/routes/admin/settings';
import { index as tagsIndex } from '@/routes/admin/tags';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavGroup, NavItem } from '@/types';

// Modules marked `disabled` are built in later tasks (see docs/02-build-prompt.md §14).
const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid, exact: true },
        ],
    },
    {
        label: 'Content',
        items: [
            { title: 'Pages', href: pagesIndex(), icon: FileText },
            { title: 'Services', href: servicesIndex(), icon: Truck },
            { title: 'Service Areas', href: areasIndex(), icon: MapPin },
            { title: 'Area × Service', href: areaServicesIndex(), icon: Grid3x3 },
            { title: 'Reviews', href: '#', icon: Star, disabled: true },
            { title: 'FAQs', href: '#', icon: CircleHelp, disabled: true },
            { title: 'Media Library', href: mediaIndex(), icon: Image },
        ],
    },
    {
        label: 'Blog',
        items: [
            { title: 'Posts', href: postsIndex(), icon: Newspaper },
            { title: 'Categories', href: blogCategoriesIndex(), icon: FolderOpen },
            { title: 'Authors', href: authorsIndex(), icon: UserRound },
            { title: 'Tags', href: tagsIndex(), icon: Tags },
        ],
    },
    {
        label: 'Leads',
        items: [
            { title: 'Quote Requests', href: '#', icon: Inbox, disabled: true },
            { title: 'Contact Messages', href: '#', icon: Mail, disabled: true },
        ],
    },
    {
        label: 'Site',
        items: [
            { title: 'Menus', href: '#', icon: Menu, disabled: true },
            { title: 'Move Types', href: moveTypesIndex(), icon: ListChecks },
            { title: 'SEO Tools', href: '#', icon: Search, disabled: true },
            { title: 'Settings', href: settingsIndex(), icon: Settings },
            { title: 'Users', href: usersIndex(), icon: Users },
        ],
    },
];

const footerNavItems: NavItem[] = [
    { title: 'View website', href: home(), icon: ExternalLink },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
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
