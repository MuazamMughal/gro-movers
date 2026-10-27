<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { report, robots } from '@/routes/admin/seo';
import redirects from '@/routes/admin/seo/redirects';

defineProps<{ current: 'report' | 'redirects' | 'robots' }>();

const tabs = [
    { key: 'report', label: 'SEO report', href: report() },
    { key: 'redirects', label: 'Redirects', href: redirects.index() },
    { key: 'robots', label: 'robots.txt & sitemap', href: robots() },
];
</script>

<template>
    <nav class="flex gap-1 overflow-x-auto border-b" aria-label="SEO tools">
        <Link
            v-for="t in tabs"
            :key="t.key"
            :href="t.href"
            class="-mb-px shrink-0 border-b-2 px-4 py-2 text-sm font-medium"
            :class="current === t.key ? 'border-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
            :aria-current="current === t.key ? 'page' : undefined"
        >
            {{ t.label }}
        </Link>
    </nav>
</template>
