<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LinkType, MenuItem, Targets } from './types';
import { linkTypes, targetsFor, typeLabel, warning } from './types';

/** Label, link target and new-tab settings of one menu item (edited in place). */
const props = defineProps<{
    item: MenuItem;
    targets: Targets;
    /** Error key prefix, e.g. "items.0" or "items.0.children.2". */
    path: string;
    errors: Record<string, string | undefined>;
}>();

const id = computed(() => `menu-item-${props.item._key}`);
const err = (field: string) => props.errors[`${props.path}.${field}`];
const options = computed(() => targetsFor(props.targets, props.item.link_type));
const hidden = computed(() => warning(props.targets, props.item));

function onType(e: Event) {
    const item = props.item;
    item.link_type = (e.target as HTMLSelectElement).value as LinkType;
    item.linkable_id = null;
    item.broken = false;
}

/** Picking a record fills an empty label with its name. */
function onTarget(e: Event) {
    const item = props.item;
    const value = Number((e.target as HTMLSelectElement).value) || null;
    item.linkable_id = value;
    item.broken = false;

    const target = options.value.find((t) => t.id === value);

    if (target && !item.label.trim()) {
        item.label = target.name;
    }
}

const selectClass = 'h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label :for="`${id}-label`">Label</Label>
            <Input :id="`${id}-label`" v-model="item.label" maxlength="60" :aria-invalid="!!err('label')" />
            <p v-if="err('label')" class="text-sm text-destructive">{{ err('label') }}</p>
        </div>
        <div class="grid gap-2">
            <Label :for="`${id}-type`">Links to</Label>
            <select :id="`${id}-type`" :value="item.link_type" :class="selectClass" @change="onType">
                <option v-for="t in linkTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
        </div>

        <div v-if="item.link_type === 'url'" class="grid gap-2 sm:col-span-2">
            <Label :for="`${id}-url`">Link</Label>
            <Input :id="`${id}-url`" v-model="item.url" maxlength="2048" placeholder="/get-a-quote or https://…" :aria-invalid="!!err('url')" />
            <p v-if="err('url')" class="text-sm text-destructive">{{ err('url') }}</p>
        </div>
        <div v-else class="grid gap-2 sm:col-span-2">
            <Label :for="`${id}-target`">{{ typeLabel(item.link_type) }}</Label>
            <select :id="`${id}-target`" :value="item.linkable_id ?? ''" :class="selectClass" :aria-invalid="!!err('linkable_id')" @change="onTarget">
                <option value="">Choose…</option>
                <option v-for="t in options" :key="t.id" :value="t.id">{{ t.name }}{{ t.live ? '' : ' (hidden)' }}</option>
            </select>
            <p v-if="err('linkable_id')" class="text-sm text-destructive">{{ err('linkable_id') }}</p>
        </div>

        <p v-if="hidden" class="flex items-start gap-2 text-sm text-amber-600 sm:col-span-2 dark:text-amber-400">
            <TriangleAlert class="mt-0.5 size-4 shrink-0" /> {{ hidden }}
        </p>

        <label class="flex items-center gap-3 text-sm sm:col-span-2">
            <Checkbox :model-value="item.target_blank" @update:model-value="(v) => (item.target_blank = v === true)" /> Open in a new tab
        </label>
    </div>
</template>
