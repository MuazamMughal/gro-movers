<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, ExternalLink, EyeOff, Pencil, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import SeoToolsNav from '@/components/seo/SeoToolsNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { report } from '@/routes/admin/seo';

type Issue = { code: string; message: string };
type Entry = {
    path: string;
    url: string;
    type: string;
    label: string;
    edit_url: string;
    title: string;
    description: string | null;
    h1: string;
    issues: Issue[];
};
type Hidden = Omit<Entry, 'issues'> & { reason: string; expected: boolean };
type Duplicate = { field: string; label: string; value: string; urls: { path: string; label: string; edit_url: string }[] };

const props = defineProps<{
    entries: Entry[];
    duplicates: Duplicate[];
    hidden: Hidden[];
    summary: { urls: number; attention: number; duplicates: number; hidden: number; hidden_unexpected: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'SEO Tools', href: report() }] },
});

type Tab = 'attention' | 'duplicates' | 'hidden' | 'all';
const tab = ref<Tab>(props.summary.attention ? 'attention' : 'all');
const tabs = computed<{ key: Tab; label: string; count: number }[]>(() => [
    { key: 'attention', label: 'Needs attention', count: props.summary.attention },
    { key: 'duplicates', label: 'Duplicates', count: props.summary.duplicates },
    { key: 'hidden', label: 'Hidden from Google', count: props.summary.hidden },
    { key: 'all', label: 'All indexable URLs', count: props.summary.urls },
]);

const list = computed(() => (tab.value === 'attention' ? props.entries.filter((e) => e.issues.length) : props.entries));
const typeLabels: Record<string, string> = { page: 'Page', service: 'Service', area: 'City', area_service: 'City service', category: 'Blog category', post: 'Post' };
const fallbackOnly = (e: Entry) => e.issues.every((i) => i.code.endsWith('_fallback'));
</script>

<template>
    <Head title="SEO Tools" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="SEO Tools"
                :description="`${summary.urls} pages can appear in Google. Every important page should have its own title, description and H1.`"
            />
            <Button variant="outline" @click="router.reload()">Refresh</Button>
        </div>

        <SeoToolsNav current="report" />

        <div class="flex gap-1 overflow-x-auto" role="tablist" aria-label="Report">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                class="flex shrink-0 items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium"
                :class="tab === t.key ? 'bg-muted' : 'text-muted-foreground hover:text-foreground'"
                @click="tab = t.key"
            >
                {{ t.label }}
                <Badge :variant="t.count && t.key !== 'all' && t.key !== 'hidden' ? 'destructive' : 'secondary'" class="px-1.5 text-[10px]">{{ t.count }}</Badge>
            </button>
        </div>

        <!-- Needs attention / all URLs -->
        <template v-if="tab === 'attention' || tab === 'all'">
            <div v-if="!list.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-14 text-center">
                <CircleCheck class="size-8 text-green-600" aria-hidden="true" />
                <p class="font-medium">Nothing to fix</p>
                <p class="text-sm text-muted-foreground">Every indexable page has its own title and description of a good length.</p>
            </div>
            <ul v-else class="divide-y overflow-hidden rounded-xl border">
                <li v-for="e in list" :key="e.path" class="space-y-1.5 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{{ typeLabels[e.type] ?? e.type }}</Badge>
                        <span class="font-medium">{{ e.label }}</span>
                        <span class="font-mono text-xs text-muted-foreground">{{ e.path }}</span>
                        <span class="ml-auto flex gap-1">
                            <Button variant="ghost" size="icon" class="size-8" as-child>
                                <a :href="e.url" target="_blank" rel="noopener" :aria-label="`View ${e.label}`"><ExternalLink class="size-4" /></a>
                            </Button>
                            <Button variant="ghost" size="icon" class="size-8" as-child>
                                <Link :href="e.edit_url" :aria-label="`Edit ${e.label}`"><Pencil class="size-4" /></Link>
                            </Button>
                        </span>
                    </div>
                    <dl class="grid gap-x-4 gap-y-0.5 text-sm sm:grid-cols-[6rem_1fr]">
                        <dt class="text-muted-foreground">Title</dt><dd class="break-words">{{ e.title }} <span class="text-xs text-muted-foreground">({{ e.title.length }})</span></dd>
                        <dt class="text-muted-foreground">Description</dt><dd class="break-words">{{ e.description ?? '—' }} <span v-if="e.description" class="text-xs text-muted-foreground">({{ e.description.length }})</span></dd>
                        <dt class="text-muted-foreground">H1</dt><dd>{{ e.h1 || '—' }}</dd>
                    </dl>
                    <ul v-if="e.issues.length" class="flex flex-wrap gap-1.5">
                        <li v-for="i in e.issues" :key="i.code" class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs" :class="fallbackOnly(e) ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'bg-destructive/10 text-destructive'">
                            <TriangleAlert class="size-3" aria-hidden="true" /> {{ i.message }}
                        </li>
                    </ul>
                </li>
            </ul>
        </template>

        <!-- Duplicates -->
        <template v-else-if="tab === 'duplicates'">
            <div v-if="!duplicates.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-14 text-center">
                <CircleCheck class="size-8 text-green-600" aria-hidden="true" />
                <p class="font-medium">No duplicates</p>
                <p class="text-sm text-muted-foreground">Every indexable page has a unique title, description and H1.</p>
            </div>
            <ul v-else class="space-y-3">
                <li v-for="(d, i) in duplicates" :key="i" class="rounded-xl border p-4">
                    <p class="text-sm"><Badge variant="destructive">{{ d.label }}</Badge> used by {{ d.urls.length }} pages:</p>
                    <p class="mt-2 font-medium break-words">“{{ d.value }}”</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="u in d.urls" :key="u.path">
                            <Link :href="u.edit_url" class="hover:underline">{{ u.label }}</Link> <span class="font-mono text-xs text-muted-foreground">{{ u.path }}</span>
                        </li>
                    </ul>
                </li>
            </ul>
        </template>

        <!-- Hidden -->
        <template v-else>
            <p class="text-sm text-muted-foreground">These pages ask Google not to index them. Only the thank-you page should be here.</p>
            <ul class="divide-y overflow-hidden rounded-xl border">
                <li v-for="h in hidden" :key="h.path" class="flex flex-wrap items-center gap-2 px-4 py-3">
                    <EyeOff class="size-4" :class="h.expected ? 'text-muted-foreground' : 'text-destructive'" aria-hidden="true" />
                    <Link :href="h.edit_url" class="font-medium hover:underline">{{ h.label }}</Link>
                    <span class="font-mono text-xs text-muted-foreground">{{ h.path }}</span>
                    <Badge v-if="h.expected" variant="secondary">Expected</Badge>
                    <Badge v-else variant="destructive">Check this</Badge>
                    <span class="ml-auto text-xs text-muted-foreground">{{ h.reason }}</span>
                </li>
            </ul>
        </template>
    </div>
</template>
