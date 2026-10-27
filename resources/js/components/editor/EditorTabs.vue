<script setup lang="ts" generic="T extends string">
import { Badge } from '@/components/ui/badge';

/**
 * Tab bar for content editors, with a red count of validation errors per tab.
 * `match` decides which error keys belong to a tab, e.g. k => k.startsWith('seo.').
 */
const props = defineProps<{
    tabs: { key: T; label: string; match?: (errorKey: string) => boolean }[];
    errors: Record<string, string | undefined>;
    label: string;
}>();

const model = defineModel<T>({ required: true });

/** Error keys not claimed by any tab's `match` belong to the first tab. */
function count(key: T): number {
    const keys = Object.keys(props.errors).filter((k) => props.errors[k]);
    const tab = props.tabs.find((t) => t.key === key);

    if (tab?.match) {
        return keys.filter(tab.match).length;
    }

    return keys.filter((k) => !props.tabs.some((t) => t.match?.(k))).length;
}

/** Switch to the first tab that has errors (call after a failed save). */
function focusErrors(): void {
    const first = props.tabs.find((t) => count(t.key) > 0);

    if (first) {
        model.value = first.key;
    }
}

defineExpose({ focusErrors });
</script>

<template>
    <div class="flex gap-1 overflow-x-auto border-b" role="tablist" :aria-label="label">
        <button
            v-for="t in tabs"
            :key="t.key"
            type="button"
            role="tab"
            :aria-selected="model === t.key"
            class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium"
            :class="model === t.key ? 'border-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
            @click="model = t.key"
        >
            {{ t.label }}
            <Badge v-if="count(t.key)" variant="destructive" class="px-1.5 text-[10px]">{{ count(t.key) }}</Badge>
        </button>
    </div>
</template>
