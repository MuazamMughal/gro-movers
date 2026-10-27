<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, GripVertical, Pencil, Plus, Trash2, Truck } from '@lucide/vue';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { create, destroy, edit, index, reorder, toggle } from '@/routes/admin/services';

type ServiceRow = {
    id: number;
    title: string;
    path: string;
    is_active: boolean;
    show_in_menu: boolean;
    show_on_home: boolean;
    areas_count: number;
    thumb: string | null;
    noindex: boolean;
    seo_missing: boolean;
};

const props = defineProps<{ services: ServiceRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Services', href: index() }] },
});

const list = ref([...props.services]);
watch(() => props.services, (s) => (list.value = [...s]));

const saveOrder = () =>
    router.post(reorder().url, { ids: list.value.map((s) => s.id) }, { preserveScroll: true, preserveState: true });

/** Keyboard-friendly reorder. */
function move(index: number, delta: number) {
    const next = [...list.value];
    const [item] = next.splice(index, 1);
    next.splice(index + delta, 0, item);
    list.value = next;
    saveOrder();
}

const flags = ['is_active', 'show_in_menu', 'show_on_home'] as const;

const flip = (service: ServiceRow, field: (typeof flags)[number]) =>
    router.patch(toggle(service.id).url, { field }, { preserveScroll: true });

const toDelete = ref<ServiceRow | null>(null);
const confirmDelete = () =>
    toDelete.value && router.delete(destroy(toDelete.value.id), { onFinish: () => (toDelete.value = null) });
</script>

<template>
    <Head title="Services" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Services" description="Each service has its own page at /services/… Drag to change the order used across the website." />
            <Button as-child>
                <Link :href="create()"><Plus class="size-4" /> New service</Link>
            </Button>
        </div>

        <div v-if="!list.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <Truck class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No services yet</p>
            <p class="text-sm text-muted-foreground">Add Local Moving, Packing, Storage… Each one gets its own SEO page.</p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <div class="hidden grid-cols-[2rem_1fr_6rem_6rem_6rem_7rem] gap-2 bg-muted/50 px-3 py-2 text-xs font-medium text-muted-foreground md:grid">
                <span></span><span>Service</span><span class="text-center">Active</span><span class="text-center">In menu</span><span class="text-center">On home</span><span></span>
            </div>
            <draggable v-model="list" item-key="id" handle=".drag-handle" :animation="150" tag="ul" class="divide-y" @end="saveOrder">
                <template #item="{ element, index }">
                    <li class="grid grid-cols-[2rem_1fr_auto] items-center gap-2 px-3 py-2 md:grid-cols-[2rem_1fr_6rem_6rem_6rem_7rem]" :class="{ 'opacity-60': !element.is_active }">
                        <span class="drag-handle cursor-grab text-muted-foreground" title="Drag to reorder" aria-hidden="true"><GripVertical class="size-4" /></span>
                        <div class="flex min-w-0 items-center gap-3">
                            <img v-if="element.thumb" :src="element.thumb" alt="" class="size-10 shrink-0 rounded object-cover" />
                            <div class="min-w-0">
                                <Link :href="edit(element.id)" class="font-medium hover:underline">{{ element.title }}</Link>
                                <div class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                    <span class="font-mono">{{ element.path }}</span>
                                    <span>· {{ element.areas_count }} {{ element.areas_count === 1 ? 'city' : 'cities' }}</span>
                                    <Badge v-if="element.noindex" variant="destructive" class="text-[10px]">NOINDEX</Badge>
                                    <Badge v-if="element.seo_missing" variant="outline" class="text-[10px] text-amber-600">SEO not set</Badge>
                                </div>
                            </div>
                        </div>
                        <label v-for="field in flags" :key="field" class="hidden justify-center md:flex">
                            <Checkbox :model-value="element[field]" :aria-label="`${field.replace(/_/g, ' ')}: ${element.title}`" @update:model-value="flip(element, field)" />
                        </label>
                        <div class="flex justify-end gap-0.5">
                            <Button variant="ghost" size="icon" class="size-8" :disabled="index === 0" :aria-label="`Move ${element.title} up`" @click="move(index, -1)">↑</Button>
                            <Button variant="ghost" size="icon" class="size-8" :disabled="index === list.length - 1" :aria-label="`Move ${element.title} down`" @click="move(index, 1)">↓</Button>
                            <Button v-if="element.is_active" variant="ghost" size="icon" class="size-8" as-child>
                                <a :href="element.path" target="_blank" rel="noopener" :aria-label="`View ${element.title}`"><ExternalLink class="size-4" /></a>
                            </Button>
                            <Button variant="ghost" size="icon" class="size-8" as-child>
                                <Link :href="edit(element.id)" :aria-label="`Edit ${element.title}`"><Pencil class="size-4" /></Link>
                            </Button>
                            <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete ${element.title}`" @click="toDelete = element"><Trash2 class="size-4 text-destructive" /></Button>
                        </div>
                    </li>
                </template>
            </draggable>
        </div>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete “${toDelete?.title}”?`"
        description="Its service page and its city pages are removed from the website. Consider making it inactive instead."
        confirm-label="Delete service"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
