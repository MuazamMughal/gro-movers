<script setup lang="ts">
import { ArrowDown, ArrowUp, Copy, Eye, EyeOff, GripVertical, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import FieldInput from './FieldInput.vue';
import type { SectionItem, SectionOptions, SectionSchema } from './types';
import { clone, uid } from './types';

const props = withDefaults(
    defineProps<{
        schema: SectionSchema;
        options: SectionOptions;
        /** Validation errors keyed like "sections.0.data.heading". */
        errors?: Record<string, string | undefined>;
        errorPrefix?: string;
    }>(),
    { errors: () => ({}), errorPrefix: 'sections' },
);

const model = defineModel<SectionItem[]>({ required: true });

// Give every section a stable client key for list rendering and drag & drop.
watch(
    model,
    (sections) => sections.forEach((s) => (s._key ??= uid())),
    { immediate: true, deep: false },
);

const selectedKey = ref<string | null>(model.value[0]?._key ?? null);
const selectedIndex = computed(() => model.value.findIndex((s) => s._key === selectedKey.value));
const selected = computed(() => model.value[selectedIndex.value] ?? null);
const selectedDef = computed(() => (selected.value ? props.schema.types[selected.value.type] : null));

const list = computed({
    get: () => model.value,
    set: (value) => (model.value = value),
});

const errorsFor = (index: number) =>
    Object.keys(props.errors).filter((k) => k.startsWith(`${props.errorPrefix}.${index}.`) && props.errors[k]).length;

// Jump to the first section with errors after a failed save.
watch(
    () => props.errors,
    () => {
        const first = model.value.findIndex((_, i) => errorsFor(i) > 0);

        if (first !== -1) {
            selectedKey.value = model.value[first]._key ?? null;
        }
    },
);

const summary = (section: SectionItem) => {
    const text = section.data.heading ?? section.data.eyebrow ?? section.data.text ?? null;

    return typeof text === 'string' && text.trim() ? text : props.schema.types[section.type]?.description ?? '';
};

// ---- Add / duplicate / remove -------------------------------------------------
const pickerOpen = ref(false);

function add(type: string) {
    const def = props.schema.types[type];
    const section: SectionItem = { id: null, type, data: clone(def.defaults), is_visible: true, _key: uid() };
    const at = selectedIndex.value === -1 ? model.value.length : selectedIndex.value + 1;
    const next = [...model.value];

    next.splice(at, 0, section);
    model.value = next;
    selectedKey.value = section._key!;
    pickerOpen.value = false;
}

function duplicate(index: number) {
    const copy: SectionItem = { ...clone(model.value[index]), id: null, _key: uid() };
    const next = [...model.value];

    next.splice(index + 1, 0, copy);
    model.value = next;
    selectedKey.value = copy._key!;
}

/** Keyboard-friendly alternative to drag & drop. */
function move(index: number, delta: number) {
    const next = [...model.value];
    const [item] = next.splice(index, 1);
    next.splice(index + delta, 0, item);
    model.value = next;
}

const toggle = (index: number) =>
    (model.value = model.value.map((s, i) => (i === index ? { ...s, is_visible: !s.is_visible } : s)));

const toRemove = ref<number | null>(null);

function remove() {
    if (toRemove.value === null) return;

    const removed = model.value[toRemove.value];
    model.value = model.value.filter((_, i) => i !== toRemove.value);

    if (removed._key === selectedKey.value) {
        selectedKey.value = model.value[Math.max(0, toRemove.value - 1)]?._key ?? null;
    }

    toRemove.value = null;
}

const updateData = (key: string, value: unknown) => {
    if (selectedIndex.value === -1) return;

    model.value = model.value.map((s, i) => (i === selectedIndex.value ? { ...s, data: { ...s.data, [key]: value } } : s));
};

/** Respect `show_if` (e.g. pickers only when "Pick services" is chosen). */
const visibleFields = computed(() =>
    (selectedDef.value?.fields ?? []).filter(
        (f) => !f.show_if || Object.entries(f.show_if).every(([k, v]) => String(selected.value?.data[k] ?? '') === v),
    ),
);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
        <!-- Section list -->
        <div class="space-y-3">
            <draggable v-model="list" item-key="_key" handle=".drag-handle" :animation="150" tag="ol" class="space-y-1.5" aria-label="Page sections">
                <template #item="{ element, index }">
                    <li
                        class="group flex items-center gap-1 rounded-lg border bg-card pr-1 text-sm transition-colors"
                        :class="{
                            'border-primary ring-1 ring-primary': element._key === selectedKey,
                            'opacity-60': !element.is_visible,
                            'border-destructive': errorsFor(index) > 0,
                        }"
                    >
                        <span class="drag-handle cursor-grab px-1.5 py-3 text-muted-foreground active:cursor-grabbing" aria-hidden="true" title="Drag to reorder">
                            <GripVertical class="size-4" />
                        </span>
                        <button type="button" class="min-w-0 flex-1 py-2 text-left" :aria-current="element._key === selectedKey" @click="selectedKey = element._key">
                            <span class="flex items-center gap-1.5 font-medium">
                                <span class="size-4 shrink-0 [&>svg]:size-4" v-html="schema.icons[schema.types[element.type]?.icon] ?? ''" />
                                {{ schema.types[element.type]?.label ?? element.type }}
                                <Badge v-if="errorsFor(index)" variant="destructive" class="px-1.5 text-[10px]">{{ errorsFor(index) }}</Badge>
                            </span>
                            <span class="block truncate text-xs text-muted-foreground">{{ summary(element) }}</span>
                        </button>
                        <Button type="button" variant="ghost" size="icon" class="size-7" :aria-label="element.is_visible ? 'Hide section' : 'Show section'" :title="element.is_visible ? 'Hide' : 'Show'" @click="toggle(index)">
                            <Eye v-if="element.is_visible" class="size-3.5" /><EyeOff v-else class="size-3.5" />
                        </Button>
                        <Button type="button" variant="ghost" size="icon" class="size-7" aria-label="Duplicate section" title="Duplicate" @click="duplicate(index)"><Copy class="size-3.5" /></Button>
                        <Button type="button" variant="ghost" size="icon" class="size-7" aria-label="Delete section" title="Delete" @click="toRemove = index"><Trash2 class="size-3.5 text-destructive" /></Button>
                    </li>
                </template>
            </draggable>

            <p v-if="!model.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                No sections yet. Add the first one.
            </p>

            <Button type="button" variant="outline" class="w-full" @click="pickerOpen = true"><Plus class="size-4" /> Add section</Button>
        </div>

        <!-- Selected section's fields -->
        <div class="min-w-0 rounded-xl border p-4 md:p-6">
            <template v-if="selected && selectedDef">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-2 border-b pb-4">
                    <div>
                        <h3 class="font-semibold">{{ selectedDef.label }}</h3>
                        <p class="text-sm text-muted-foreground">{{ selectedDef.description }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <Badge v-if="!selected.is_visible" variant="secondary">Hidden on the website</Badge>
                        <Button type="button" variant="outline" size="sm" :disabled="selectedIndex === 0" @click="move(selectedIndex, -1)">
                            <ArrowUp class="size-4" /> Move up
                        </Button>
                        <Button type="button" variant="outline" size="sm" :disabled="selectedIndex === model.length - 1" @click="move(selectedIndex, 1)">
                            <ArrowDown class="size-4" /> Move down
                        </Button>
                    </div>
                </div>
                <div :key="selected._key" class="grid gap-6">
                    <FieldInput
                        v-for="field in visibleFields"
                        :key="field.key"
                        :field="field"
                        :model-value="selected.data[field.key]"
                        :error-key="`${errorPrefix}.${selectedIndex}.data.${field.key}`"
                        :errors="errors"
                        :icons="schema.icons"
                        :options="options"
                        @update:model-value="(v: unknown) => updateData(field.key, v)"
                    />
                </div>
            </template>
            <p v-else class="py-12 text-center text-sm text-muted-foreground">Select a section to edit it.</p>
        </div>
    </div>

    <Dialog v-model:open="pickerOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Add a section</DialogTitle>
                <DialogDescription>It is added below the selected section. You can drag it anywhere afterwards.</DialogDescription>
            </DialogHeader>
            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="def in schema.types"
                    :key="def.type"
                    type="button"
                    class="flex gap-3 rounded-lg border p-3 text-left hover:border-primary hover:bg-muted/50"
                    @click="add(def.type)"
                >
                    <span class="mt-0.5 size-6 shrink-0 text-primary [&>svg]:size-6" v-html="schema.icons[def.icon] ?? ''" />
                    <span>
                        <span class="block font-medium">{{ def.label }}</span>
                        <span class="block text-xs text-muted-foreground">{{ def.description }}</span>
                    </span>
                </button>
            </div>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="toRemove !== null"
        :title="`Delete this ${toRemove !== null ? (schema.types[model[toRemove]?.type]?.label ?? 'section') : 'section'} section?`"
        description="Its content is removed when you save the page."
        confirm-label="Delete section"
        destructive
        @update:open="(open) => !open && (toRemove = null)"
        @confirm="remove"
    />
</template>
