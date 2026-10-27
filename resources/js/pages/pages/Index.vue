<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, destroy, edit, index } from '@/routes/admin/pages';

type PageRow = {
    id: number;
    title: string;
    path: string | null;
    status: 'draft' | 'published';
    is_system: boolean;
    system_label: string | null;
    admin_note: string | null;
    noindex: boolean;
    seo_missing: boolean;
    sections_count: number;
    updated_at: string | null;
};

const props = defineProps<{
    pages: PageRow[];
    filters: { q: string | null; status: string | null };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pages', href: index() }] },
});

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

function applyFilters() {
    const query: Record<string, string> = {};

    if (q.value) query.q = q.value;
    if (status.value) query.status = status.value;

    router.get(index({ query }).url, {}, { preserveState: true, preserveScroll: true, replace: true });
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});
watch(status, applyFilters);

const toDelete = ref<PageRow | null>(null);
const deleting = ref(false);

const confirmDelete = () =>
    toDelete.value &&
    router.delete(destroy(toDelete.value.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });

const formatDate = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(new Date(iso)) : '—';
</script>

<template>
    <Head title="Pages" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Pages" description="The home page, fixed pages like About and Contact, and any extra pages you add." />
            <Button as-child>
                <Link :href="create()"><Plus class="size-4" /> New page</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="relative min-w-60 flex-1">
                <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                <Input v-model="q" class="pl-8" placeholder="Search by title or URL" aria-label="Search pages" />
            </div>
            <select v-model="status" class="h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Page</th>
                        <th class="px-4 py-3 font-medium">URL</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Updated</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="!pages.length">
                        <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No pages match these filters.</td>
                    </tr>
                    <tr v-for="page in pages" :key="page.id" class="hover:bg-muted/30">
                        <td class="px-4 py-3">
                            <Link :href="edit(page.id)" class="font-medium hover:underline">{{ page.title }}</Link>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge v-if="page.is_system" variant="secondary" class="text-[10px]">System</Badge>
                                <Badge v-if="page.noindex" variant="destructive" class="text-[10px]">NOINDEX</Badge>
                                <Badge v-if="page.admin_note" variant="outline" class="border-amber-400 text-[10px] text-amber-700 dark:text-amber-400" :title="page.admin_note">Note</Badge>
                                <Badge v-if="page.seo_missing" variant="outline" class="text-[10px] text-amber-600">SEO not set</Badge>
                                <Badge v-if="!page.sections_count" variant="outline" class="text-[10px]">Empty</Badge>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ page.path ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="page.status === 'published' ? 'default' : 'outline'">{{ page.status === 'published' ? 'Published' : 'Draft' }}</Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ formatDate(page.updated_at) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button v-if="page.status === 'published' && page.path" variant="ghost" size="icon" as-child>
                                    <a :href="page.path" target="_blank" rel="noopener" :aria-label="`View ${page.title} on the website`"><ExternalLink class="size-4" /></a>
                                </Button>
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="edit(page.id)" :aria-label="`Edit ${page.title}`"><Pencil class="size-4" /></Link>
                                </Button>
                                <Button v-if="!page.is_system" variant="ghost" size="icon" :aria-label="`Delete ${page.title}`" @click="toDelete = page">
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete “${toDelete?.title}”?`"
        description="The page is removed from the website. Visitors to its URL will see a 'page not found' error unless you add a redirect."
        confirm-label="Delete page"
        destructive
        :processing="deleting"
        @update:open="(open) => !open && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
