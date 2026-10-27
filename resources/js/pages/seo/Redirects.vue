<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, Plus, Search, Trash2, Upload } from '@lucide/vue';
import { ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import SeoToolsNav from '@/components/seo/SeoToolsNav.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { report } from '@/routes/admin/seo';
import redirectRoutes from '@/routes/admin/seo/redirects';

type Row = { id: number; from_path: string; to_path: string; status_code: number; hits: number; last_hit_at: string | null; is_active: boolean };
type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number; prev_page_url: string | null; next_page_url: string | null };

const props = defineProps<{ redirects: Paginated<Row>; filters: { q: string } }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'SEO Tools', href: report() }, { title: 'Redirects', href: redirectRoutes.index() }] },
});

const q = ref(props.filters.q);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(redirectRoutes.index().url, q.value ? { q: q.value } : {}, { preserveState: true, replace: true }), 300);
});

const form = useForm({ from_path: '', to_path: '', status_code: 301, is_active: true });
const add = () => form.post(redirectRoutes.store().url, { preserveScroll: true, onSuccess: () => form.reset() });

const toggle = (r: Row) => router.put(redirectRoutes.update(r.id).url, { ...r, is_active: !r.is_active }, { preserveScroll: true });

const importForm = useForm<{ file: File | null }>({ file: null });
const fileInput = ref<HTMLInputElement | null>(null);
function onFile(e: Event) {
    importForm.file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (importForm.file) importForm.post(redirectRoutes.import().url, { forceFormData: true, preserveScroll: true, onFinish: () => fileInput.value && (fileInput.value.value = '') });
}

const toDelete = ref<Row | null>(null);
const selectClass = 'h-9 rounded-md border bg-transparent px-2 text-sm shadow-xs';
</script>

<template>
    <Head title="Redirects" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="SEO Tools" description="Send visitors and Google from old URLs to the right page. URL changes in the admin add redirects automatically." />
            <div>
                <input ref="fileInput" type="file" accept=".csv,text/csv" class="sr-only" aria-label="CSV file to import" @change="onFile" />
                <Button variant="outline" :disabled="importForm.processing" @click="fileInput?.click()"><Upload class="size-4" /> Import CSV</Button>
            </div>
        </div>

        <SeoToolsNav current="redirects" />

        <p class="text-sm text-muted-foreground">CSV import: one redirect per line as <code class="rounded bg-muted px-1">old-url,new-url</code> or <code class="rounded bg-muted px-1">old-url,new-url,302</code>. Existing redirects for the same old URL are updated.</p>
        <p v-if="importForm.errors.file" class="-mt-4 text-sm text-destructive">{{ importForm.errors.file }}</p>

        <form class="grid gap-2 rounded-xl border p-4 md:grid-cols-[1fr_auto_1fr_auto_auto]" @submit.prevent="add">
            <div>
                <Input v-model="form.from_path" placeholder="/old-page" aria-label="Old URL" :aria-invalid="!!form.errors.from_path" />
                <p v-if="form.errors.from_path" class="mt-1 text-sm text-destructive">{{ form.errors.from_path }}</p>
            </div>
            <ArrowRight class="hidden size-4 self-center text-muted-foreground md:block" aria-hidden="true" />
            <div>
                <Input v-model="form.to_path" placeholder="/new-page or https://…" aria-label="New URL" :aria-invalid="!!form.errors.to_path" />
                <p v-if="form.errors.to_path" class="mt-1 text-sm text-destructive">{{ form.errors.to_path }}</p>
            </div>
            <select v-model.number="form.status_code" :class="selectClass" aria-label="Redirect type">
                <option :value="301">301 permanent</option>
                <option :value="302">302 temporary</option>
            </select>
            <Button :disabled="form.processing"><Plus class="size-4" /> Add</Button>
        </form>

        <div class="relative">
            <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
            <Input v-model="q" class="pl-8" placeholder="Search old or new URL" aria-label="Search redirects" />
        </div>

        <p v-if="!redirects.data.length" class="rounded-xl border border-dashed py-12 text-center text-sm text-muted-foreground">No redirects yet.</p>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                    <tr><th class="px-3 py-2 font-medium">Old URL</th><th class="px-3 py-2 font-medium">New URL</th><th class="px-3 py-2 font-medium">Type</th><th class="px-3 py-2 text-right font-medium">Hits</th><th class="px-3 py-2 font-medium">Active</th><th></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="r in redirects.data" :key="r.id" :class="{ 'opacity-60': !r.is_active }">
                        <td class="px-3 py-2 font-mono text-xs break-all">{{ r.from_path }}</td>
                        <td class="px-3 py-2 font-mono text-xs break-all">{{ r.to_path }}</td>
                        <td class="px-3 py-2">{{ r.status_code }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ r.hits }}<span v-if="r.last_hit_at" class="block text-xs text-muted-foreground">{{ r.last_hit_at }}</span></td>
                        <td class="px-3 py-2"><Checkbox :model-value="r.is_active" :aria-label="`Active: ${r.from_path}`" @update:model-value="toggle(r)" /></td>
                        <td class="px-3 py-2 text-right"><Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete redirect ${r.from_path}`" @click="toDelete = r"><Trash2 class="size-4 text-destructive" /></Button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="redirects.last_page > 1" class="flex items-center justify-between text-sm" aria-label="Pagination">
            <Button variant="outline" size="sm" :disabled="!redirects.prev_page_url" @click="redirects.prev_page_url && router.visit(redirects.prev_page_url)">← Previous</Button>
            <span class="text-muted-foreground">Page {{ redirects.current_page }} of {{ redirects.last_page }} · {{ redirects.total }} redirects</span>
            <Button variant="outline" size="sm" :disabled="!redirects.next_page_url" @click="redirects.next_page_url && router.visit(redirects.next_page_url)">Next →</Button>
        </nav>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete the redirect from ${toDelete?.from_path}?`"
        description="Visitors to the old URL will get a 'page not found' instead."
        confirm-label="Delete"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="toDelete && router.delete(redirectRoutes.destroy(toDelete.id).url, { preserveScroll: true, onFinish: () => (toDelete = null) })"
    />
</template>
