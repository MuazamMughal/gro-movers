<script setup lang="ts">
import { ArrowDown, ArrowUp, X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { PickerOption } from './types';

const props = defineProps<{ id: string; options: PickerOption[]; noun: string }>();
const model = defineModel<number[]>({ default: () => [] });

const byId = computed(() => new Map(props.options.map((o) => [o.id, o])));
const selected = computed(() => (model.value ?? []).map((id) => byId.value.get(id)).filter((o): o is PickerOption => !!o));
const available = computed(() => props.options.filter((o) => !(model.value ?? []).includes(o.id)));

const add = (e: Event) => {
    const id = Number((e.target as HTMLSelectElement).value);

    if (id) {
        model.value = [...(model.value ?? []), id];
    }

    (e.target as HTMLSelectElement).value = '';
};

const remove = (id: number) => (model.value = model.value.filter((x) => x !== id));

const move = (index: number, delta: number) => {
    const next = [...model.value];
    const [item] = next.splice(index, 1);
    next.splice(index + delta, 0, item);
    model.value = next;
};
</script>

<template>
    <div class="space-y-2">
        <ol v-if="selected.length" class="divide-y rounded-md border text-sm">
            <li v-for="(option, i) in selected" :key="option.id" class="flex items-center gap-2 px-3 py-1.5">
                <span class="flex-1 truncate">
                    {{ option.label }}
                    <span v-if="option.hint" class="text-xs text-muted-foreground">· {{ option.hint }}</span>
                </span>
                <Button type="button" variant="ghost" size="icon" class="size-7" :disabled="i === 0" :aria-label="`Move ${option.label} up`" @click="move(i, -1)"><ArrowUp class="size-3.5" /></Button>
                <Button type="button" variant="ghost" size="icon" class="size-7" :disabled="i === selected.length - 1" :aria-label="`Move ${option.label} down`" @click="move(i, 1)"><ArrowDown class="size-3.5" /></Button>
                <Button type="button" variant="ghost" size="icon" class="size-7" :aria-label="`Remove ${option.label}`" @click="remove(option.id)"><X class="size-3.5" /></Button>
            </li>
        </ol>
        <p v-else class="text-sm text-muted-foreground">No {{ noun }} picked yet.</p>
        <select
            :id="id"
            class="h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs"
            :disabled="!available.length"
            @change="add"
        >
            <option value="">{{ available.length ? `Add ${noun}…` : `All ${noun} added` }}</option>
            <option v-for="option in available" :key="option.id" :value="option.id">{{ option.label }}</option>
        </select>
    </div>
</template>
