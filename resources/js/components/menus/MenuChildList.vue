<script setup lang="ts">
import { CornerDownRight, GripVertical, Outdent, Plus, Trash2, TriangleAlert } from '@lucide/vue';
import draggable from 'vuedraggable';
import { Button } from '@/components/ui/button';
import MenuItemFields from './MenuItemFields.vue';
import type { MenuItem, Targets } from './types';
import { summary, warning } from './types';

/** The dropdown items of one top-level menu item. */
const props = defineProps<{
    parent: MenuItem;
    /** Error key prefix of the parent, e.g. "items.3". */
    path: string;
    targets: Targets;
    errors: Record<string, string | undefined>;
    expanded: Set<string>;
}>();

const emit = defineEmits<{ toggle: [key: string]; add: []; outdent: [index: number] }>();

function move(j: number, delta: number) {
    const list = props.parent.children;
    const [item] = list.splice(j, 1);
    list.splice(j + delta, 0, item);
}

const remove = (j: number) => props.parent.children.splice(j, 1);
const hasErrors = (j: number) => Object.keys(props.errors).some((k) => k.startsWith(`${props.path}.children.${j}.`));
</script>

<template>
    <div class="border-t bg-muted/20 px-3 py-2">
        <draggable v-model="parent.children" item-key="_key" handle=".drag-handle" :animation="150" tag="ul" class="space-y-1.5">
            <template #item="{ element, index }">
                <li class="rounded-lg border bg-background" :class="{ 'border-destructive': hasErrors(index) }">
                    <div class="flex items-center gap-1 px-2 py-1.5">
                        <CornerDownRight class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <span class="drag-handle cursor-grab px-1 text-muted-foreground" aria-hidden="true" title="Drag to reorder"><GripVertical class="size-4" /></span>
                        <button type="button" class="min-w-0 flex-1 text-left" :aria-expanded="expanded.has(element._key)" @click="emit('toggle', element._key)">
                            <span class="block truncate text-sm font-medium">{{ element.label || 'Untitled item' }}</span>
                            <span class="block truncate text-xs text-muted-foreground">{{ summary(targets, element) }}</span>
                        </button>
                        <TriangleAlert v-if="warning(targets, element)" class="size-4 shrink-0 text-amber-500" aria-label="Hidden on the website" />
                        <Button variant="ghost" size="icon" class="size-7" :disabled="index === 0" :aria-label="`Move ${element.label} up`" @click="move(index, -1)">↑</Button>
                        <Button variant="ghost" size="icon" class="size-7" :disabled="index === parent.children.length - 1" :aria-label="`Move ${element.label} down`" @click="move(index, 1)">↓</Button>
                        <Button variant="ghost" size="icon" class="size-7" :aria-label="`Move ${element.label} out of the dropdown`" title="Move out of the dropdown" @click="emit('outdent', index)"><Outdent class="size-4" /></Button>
                        <Button variant="ghost" size="icon" class="size-7" :aria-label="`Delete ${element.label}`" @click="remove(index)"><Trash2 class="size-4 text-destructive" /></Button>
                    </div>
                    <div v-if="expanded.has(element._key)" class="border-t px-3 py-3">
                        <MenuItemFields :item="element" :targets="targets" :path="`${path}.children.${index}`" :errors="errors" />
                    </div>
                </li>
            </template>
        </draggable>
        <Button type="button" variant="ghost" size="sm" class="mt-1" @click="emit('add')"><Plus class="size-4" /> Add to dropdown</Button>
    </div>
</template>
