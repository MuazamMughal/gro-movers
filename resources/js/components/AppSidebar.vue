<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CircleHelp,
    ExternalLink,
    FileText,
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
    Truck,
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
import { index as settingsIndex } from '@/routes/admin/settings';
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
            { title: 'Pages', href: '#', icon: FileText, disabled: true },
            { title: 'Services', href: '#', icon: Truck, disabled: true },
            { title: 'Service Areas', href: '#', icon: MapPin, disabled: true },
            { title: 'Area × Service', href: '#', icon: Grid3x3, disabled: true },
            { title: 'Blog', href: '#', icon: Newspaper, disabled: true },
            { title: 'Reviews', href: '#', icon: Star, disabled: true },
            { title: 'FAQs', href: '#', icon: CircleHelp, disabled: true },
            { title: 'Media Library', href: '#', icon: Image, disabled: true },
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
            { title: 'Move Types', href: '#', icon: ListChecks, disabled: true },
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
