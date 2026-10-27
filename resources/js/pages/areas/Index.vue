<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Grid3x3, GripVertical, MapPin, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { index as gridIndex } from '@/routes/admin/area-services';
import { create, destroy, edit, index, reorder, toggle } from '@/routes/admin/areas';

type AreaRow = {
    id: number;
    name: string;
    state_name: string;
    path: string;
    is_active: boolean;
    show_in_menu: boolean;
    services_count: number;
    noindex: boolean;
    seo_missing: boolean;
};

const props = defineProps<{ areas: AreaRow[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Service Areas', href: index() }] },
});

const list = ref([...props.areas]);
watch(() => props.areas, (a) => (list.value = [...a]));

const saveOrder = () => router.post(reorder().url, { ids: list.value.map((a) => a.id) }, { preserveScroll: true, preserveState: true });

function move(i: number, delta: number) {
    const next = [...list.value];
    const [item] = next.splice(i, 1);
    next.splice(i + delta, 0, item);
    list.value = next;
    saveOrder();
}

const flags = ['is_active', 'show_in_menu'] as const;
const flip = (area: AreaRow, field: (typeof flags)[number]) => router.patch(toggle(area.id).url, { field }, { preserveScroll: true });

const toDelete = ref<AreaRow | null>(null);
const confirmDelete = () => toDelete.value && router.delete(destroy(toDelete.value.id), { onFinish: () => (toDelete.value = null) });
</script>

<template>
    <Head title="Service Areas" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Service Areas" description="Cities you serve. Each gets a page plus one page per service offered there." />
            <div class="flex gap-2">
                <Button variant="outline" as-child><Link :href="gridIndex()"><Grid3x3 class="size-4" /> City × service pages</Link></Button>
                <Button as-child><Link :href="create()"><Plus class="size-4" /> New area</Link></Button>
            </div>
        </div>

        <div v-if="!list.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <MapPin class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No service areas yet</p>
            <p class="text-sm text-muted-foreground">Add Dallas, Plano, Irving… Each city gets its own local SEO pages.</p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <div class="hidden grid-cols-[2rem_1fr_6rem_6rem_7rem] gap-2 bg-muted/50 px-3 py-2 text-xs font-medium text-muted-foreground md:grid">
                <span></span><span>City</span><span class="text-center">Active</span><span class="text-center">In menu</span><span></span>
            </div>
            <draggable v-model="list" item-key="id" handle=".drag-handle" :animation="150" tag="ul" class="divide-y" @end="saveOrder">
                <template #item="{ element, index }">
                    <li class="grid grid-cols-[2rem_1fr_auto] items-center gap-2 px-3 py-2 md:grid-cols-[2rem_1fr_6rem_6rem_7rem]" :class="{ 'opacity-60': !element.is_active }">
                        <span class="drag-handle cursor-grab text-muted-foreground" aria-hidden="true" title="Drag to reorder"><GripVertical class="size-4" /></span>
                        <div class="min-w-0">
                            <Link :href="edit(element.id)" class="font-medium hover:underline">{{ element.name }}</Link>
                            <div class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                <span class="font-mono">{{ element.path }}</span>
                                <span>· {{ element.services_count }} {{ element.services_count === 1 ? 'service' : 'services' }}</span>
                                <Badge v-if="element.noindex" variant="destructive" class="text-[10px]">NOINDEX</Badge>
                                <Badge v-if="element.seo_missing" variant="outline" class="text-[10px] text-amber-600">SEO not set</Badge>
                            </div>
                        </div>
                        <label v-for="field in flags" :key="field" class="hidden justify-center md:flex">
                            <Checkbox :model-value="element[field]" :aria-label="`${field.replace(/_/g, ' ')}: ${element.name}`" @update:model-value="flip(element, field)" />
                        </label>
                        <div class="flex justify-end gap-0.5">
                            <Button variant="ghost" size="icon" class="size-8" :disabled="index === 0" :aria-label="`Move ${element.name} up`" @click="move(index, -1)">↑</Button>
                            <Button variant="ghost" size="icon" class="size-8" :disabled="index === list.length - 1" :aria-label="`Move ${element.name} down`" @click="move(index, 1)">↓</Button>
                            <Button v-if="element.is_active" variant="ghost" size="icon" class="size-8" as-child>
                                <a :href="element.path" target="_blank" rel="noopener" :aria-label="`View ${element.name}`"><ExternalLink class="size-4" /></a>
                            </Button>
                            <Button variant="ghost" size="icon" class="size-8" as-child><Link :href="edit(element.id)" :aria-label="`Edit ${element.name}`"><Pencil class="size-4" /></Link></Button>
                            <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete ${element.name}`" @click="toDelete = element"><Trash2 class="size-4 text-destructive" /></Button>
                        </div>
                    </li>
                </template>
            </draggable>
        </div>
    </div>

    <ConfirmDialog
        :open="toDelete !== null"
        :title="`Delete ${toDelete?.name}?`"
        description="The city page and all its service pages are removed from the website. Consider making it inactive instead."
        confirm-label="Delete area"
        destructive
        @update:open="(o) => !o && (toDelete = null)"
        @confirm="confirmDelete"
    />
</template>
