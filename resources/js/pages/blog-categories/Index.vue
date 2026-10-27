<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, FolderOpen, GripVertical, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index, reorder } from '@/routes/admin/blog-categories';

type CategoryRow = {
    id: number;
    name: string;
    path: string;
    posts_count: number;
    live_count: number;
    noindex: boolean;
    seo_missing: boolean;
};

const props = defineProps<{ categories: CategoryRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Blog Categories', href: index() }] },
});

const list = ref([...props.categories]);
watch(() => props.categories, (c) => (list.value = [...c]));

const saveOrder = () => router.post(reorder().url, { ids: list.value.map((c) => c.id) }, { preserveScroll: true, preserveState: true });

function move(i: number, delta: number) {
    const next = [...list.value];
    const [item] = next.splice(i, 1);
    next.splice(i + delta, 0, item);
    list.value = next;
    saveOrder();
}

const toDelete = ref<CategoryRow | null>(null);
const confirmDelete = () =>
    toDelete.value && router.delete(destroy(toDelete.value.id), { preserveScroll: true, onFinish: () => (toDelete.value = null) });
</script>

<template>
    <Head title="Blog Categories" />

    <div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Blog Categories" description="Each category has its own page at /blog/category/… Drag to set the order of the blog's category menu." />
            <Button as-child>
                <Link :href="create()"><Plus class="size-4" /> New category</Link>
            </Button>
        </div>

        <div v-if="!list.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <FolderOpen class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No categories yet</p>
            <p class="text-sm text-muted-foreground">Every post needs a category, so add one first.</p>
        </div>

        <draggable v-else v-model="list" item-key="id" handle=".drag-handle" :animation="150" tag="ul" class="divide-y rounded-xl border" @end="saveOrder">
            <template #item="{ element, index: i }">
                <li class="flex items-center gap-2 px-3 py-2.5">
                    <span class="drag-handle cursor-grab text-muted-foreground" aria-hidden="true" title="Drag to reorder"><GripVertical class="size-4" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="edit(element.id)" class="font-medium hover:underline">{{ element.name }}</Link>
                            <Badge v-if="element.noindex" variant="destructive">NOINDEX</Badge>
                            <Badge v-if="element.seo_missing" variant="outline" class="text-amber-600 dark:text-amber-400">SEO not set</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ element.path }} · {{ element.live_count }} live of {{ element.posts_count }} {{ element.posts_count === 1 ? 'post' : 'posts' }}</p>
                    </div>
                    <Button variant="ghost" size="icon" class="size-8" :disabled="i === 0" :aria-label="`Move ${element.name} up`" @click="move(i, -1)">↑</Button>
                    <Button variant="ghost" size="icon" class="size-8" :disabled="i === list.length - 1" :aria-label="`Move ${element.name} down`" @click="move(i, 1)">↓</Button>
                    <Button variant="ghost" size="icon" class="size-8" as-child>
                        <a :href="element.path" target="_blank" rel="noopener" :aria-label="`View ${element.name}`"><ExternalLink class="size-4" /></a>
                    </Button>
                    <Button variant="ghost" size="icon" class="size-8" as-child>
                        <Link :href="edit(element.id)" :aria-label="`Edit ${element.name}`"><Pencil class="size-4" /></Link>
                    </Button>
                    <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete ${element.name}`" @click="toDelete = element"><Trash2 class="size-4 text-destructive" /></Button>
                </li>
            </template>
        </draggable>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete “${toDelete?.name}”?`"
        :description="toDelete?.posts_count ? 'This category still has posts. Move them to another category first.' : 'Its page is removed from the website.'"
        confirm-label="Delete"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
