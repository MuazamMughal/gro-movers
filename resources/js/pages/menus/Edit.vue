<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, GripVertical, Indent, Plus, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';
import Heading from '@/components/Heading.vue';
import MenuChildList from '@/components/menus/MenuChildList.vue';
import MenuItemFields from '@/components/menus/MenuItemFields.vue';
import type { MenuItem, Targets } from '@/components/menus/types';
import { summary, warning } from '@/components/menus/types';
import { uid } from '@/components/sections/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { edit, index, update } from '@/routes/admin/menus';

type ServerItem = Omit<MenuItem, '_key' | 'children'> & { id: number; children?: ServerItem[] };

const props = defineProps<{
    menu: { id: number; title: string; location: string; location_label: string; nested: boolean };
    menus: { id: number; label: string; items_count: number }[];
    items: ServerItem[];
    targets: Targets;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Menus', href: index() }] },
});

const toItem = (i: ServerItem): MenuItem => ({
    _key: uid(),
    label: i.label,
    link_type: i.link_type,
    linkable_id: i.linkable_id,
    url: i.url,
    auto_children: i.auto_children,
    target_blank: i.target_blank,
    broken: i.broken,
    children: (i.children ?? []).map(toItem),
});

// The item tree lives outside useForm (its recursive type is too deep for the form helpers);
// the form carries the title, errors and the processing state.
const items = ref<MenuItem[]>(props.items.map(toItem));
const form = useForm({ title: props.menu.title });

/** What the server needs, without client-only keys. */
const payload = (list: MenuItem[]): Record<string, unknown>[] =>
    list.map((i) => ({
        label: i.label,
        link_type: i.link_type,
        linkable_id: i.link_type === 'url' ? null : i.linkable_id,
        url: i.link_type === 'url' ? i.url : null,
        target_blank: i.target_blank,
        ...(props.menu.nested ? { auto_children: i.auto_children, children: i.auto_children ? [] : payload(i.children) } : {}),
    }));

const saved = ref(JSON.stringify(payload(items.value)));
const dirty = computed(() => form.isDirty || JSON.stringify(payload(items.value)) !== saved.value);

useUnsavedGuard(() => dirty.value && !form.processing);

const autoOptions: { value: 'services' | 'areas' | null; label: string }[] = [
    { value: null, label: 'Items I add below' },
    { value: 'services', label: 'All services (automatic)' },
    { value: 'areas', label: 'All service areas (automatic)' },
];

const expanded = ref<Set<string>>(new Set());
const toggle = (key: string) => (expanded.value.has(key) ? expanded.value.delete(key) : expanded.value.add(key));

const blank = (): MenuItem => ({ _key: uid(), label: '', link_type: 'page', linkable_id: null, url: null, auto_children: null, target_blank: false, children: [] });

function addItem(parent?: MenuItem) {
    const item = blank();
    (parent ? parent.children : items.value).push(item);
    expanded.value.add(item._key);
}

function move(i: number, delta: number) {
    const [item] = items.value.splice(i, 1);
    items.value.splice(i + delta, 0, item);
}

/** Put a top-level item (and its own dropdown items) into the dropdown of the item above. */
function indent(i: number) {
    const [item] = items.value.splice(i, 1);
    const parent = items.value[i - 1];
    parent.children.push({ ...item, auto_children: null, children: [] }, ...item.children);
}

/** Move a dropdown item up to the top level, right after its parent. */
function outdent(parentIndex: number, childIndex: number) {
    const [child] = items.value[parentIndex].children.splice(childIndex, 1);
    items.value.splice(parentIndex + 1, 0, child);
}

const errors = computed(() => form.errors as Record<string, string | undefined>);
const hasErrors = (path: string) => Object.keys(errors.value).some((k) => k.startsWith(path + '.'));

function submit() {
    form.transform((data) => ({ title: data.title, items: payload(items.value) })).put(update(props.menu.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
            items.value = props.items.map(toItem);
            saved.value = JSON.stringify(payload(items.value));
            expanded.value = new Set();
        },
        onError: () =>
            items.value.forEach((item, i) => {
                if (hasErrors(`items.${i}`)) expanded.value.add(item._key);
                item.children.forEach((c, j) => hasErrors(`items.${i}.children.${j}`) && expanded.value.add(c._key));
            }),
    });
}

const selectClass = 'h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs';
const isFooter = computed(() => props.menu.location.startsWith('footer'));
</script>

<template>
    <Head :title="`Menus: ${menu.location_label}`" />

    <div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Menus" description="Links in the website header, footer columns and mobile menu. Hidden or unpublished pages drop out of menus automatically." />
            <div class="flex items-center gap-2">
                <span v-if="dirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button type="button" :disabled="form.processing" @click="submit">Save menu</Button>
            </div>
        </div>

        <nav class="flex gap-1 overflow-x-auto border-b" aria-label="Menu locations">
            <Link
                v-for="m in menus"
                :key="m.id"
                :href="edit(m.id)"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium"
                :class="m.id === menu.id ? 'border-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                :aria-current="m.id === menu.id ? 'page' : undefined"
            >
                {{ m.label }}
                <span class="text-xs text-muted-foreground">{{ m.items_count }}</span>
            </Link>
        </nav>

        <div class="grid gap-2 sm:max-w-sm">
            <Label for="menu-title">{{ isFooter ? 'Column heading' : 'Menu name (for admins)' }}</Label>
            <Input id="menu-title" v-model="form.title" maxlength="60" :aria-invalid="!!form.errors.title" />
            <p v-if="isFooter" class="text-xs text-muted-foreground">Shown above this column in the footer. Footer columns are simple lists (no dropdowns).</p>
            <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
        </div>

        <p v-if="menu.location === 'mobile'" class="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
            Leave the mobile menu empty to use the header menu on phones.
        </p>

        <div v-if="!items.length" class="rounded-xl border border-dashed py-12 text-center text-sm text-muted-foreground">This menu is empty.</div>

        <draggable v-model="items" item-key="_key" handle=".drag-handle" :animation="150" tag="ul" class="space-y-2">
            <template #item="{ element, index }">
                <li class="rounded-xl border" :class="{ 'border-destructive': hasErrors(`items.${index}`) }">
                    <div class="flex items-center gap-1 px-2 py-2">
                        <span class="drag-handle cursor-grab px-1 text-muted-foreground" aria-hidden="true" title="Drag to reorder"><GripVertical class="size-4" /></span>
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" :aria-expanded="expanded.has(element._key)" @click="toggle(element._key)">
                            <component :is="expanded.has(element._key) ? ChevronDown : ChevronRight" class="size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ element.label || 'Untitled item' }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ summary(targets, element) }}</span>
                            </span>
                        </button>
                        <TriangleAlert v-if="warning(targets, element)" class="size-4 shrink-0 text-amber-500" aria-label="Hidden on the website" />
                        <Badge v-if="element.auto_children" variant="secondary" class="shrink-0">Auto dropdown</Badge>
                        <Badge v-else-if="element.children.length" variant="secondary" class="shrink-0">{{ element.children.length }} in dropdown</Badge>
                        <Button variant="ghost" size="icon" class="size-8" :disabled="index === 0" :aria-label="`Move ${element.label} up`" @click="move(index, -1)">↑</Button>
                        <Button variant="ghost" size="icon" class="size-8" :disabled="index === items.length - 1" :aria-label="`Move ${element.label} down`" @click="move(index, 1)">↓</Button>
                        <Button
                            v-if="menu.nested"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :disabled="index === 0 || !!items[index - 1]?.auto_children"
                            :aria-label="`Put ${element.label} in the dropdown of the item above`"
                            title="Put in the dropdown of the item above"
                            @click="indent(index)"
                        >
                            <Indent class="size-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="size-8" :aria-label="`Delete ${element.label}`" @click="items.splice(index, 1)"><Trash2 class="size-4 text-destructive" /></Button>
                    </div>

                    <div v-if="expanded.has(element._key)" class="space-y-4 border-t px-4 py-4">
                        <MenuItemFields :item="element" :targets="targets" :path="`items.${index}`" :errors="errors" />

                        <div v-if="menu.nested" class="grid gap-2 sm:max-w-sm">
                            <Label :for="`menu-item-${element._key}-auto`">Dropdown</Label>
                            <select :id="`menu-item-${element._key}-auto`" v-model="element.auto_children" :class="selectClass">
                                <option v-for="o in autoOptions" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
                            </select>
                            <p v-if="element.auto_children" class="text-xs text-muted-foreground">
                                Lists every active {{ element.auto_children === 'services' ? 'service' : 'service area' }} with “Show in menu” on, in their admin order.
                            </p>
                        </div>
                    </div>

                    <MenuChildList
                        v-if="menu.nested && !element.auto_children && (element.children.length || expanded.has(element._key))"
                        :parent="element"
                        :path="`items.${index}`"
                        :targets="targets"
                        :errors="errors"
                        :expanded="expanded"
                        @toggle="toggle"
                        @add="addItem(element)"
                        @outdent="(j) => outdent(index, j)"
                    />
                </li>
            </template>
        </draggable>

        <div>
            <Button type="button" variant="outline" @click="addItem()"><Plus class="size-4" /> Add menu item</Button>
        </div>
    </div>
</template>
