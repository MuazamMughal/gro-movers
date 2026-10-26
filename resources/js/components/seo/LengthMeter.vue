<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    length: number;
    limit: number;
    /** Below this many characters the text is flagged as short. */
    min: number;
    /** True when the field is empty and the fallback is shown instead. */
    usingFallback?: boolean;
}>();

const state = computed(() => {
    if (props.usingFallback) {
        return { tone: 'muted', text: 'Using the automatic value' };
    }

    if (props.length > props.limit) {
        return { tone: 'bad', text: `Too long: Google will cut it off after ~${props.limit} characters` };
    }

    if (props.length < props.min) {
        return { tone: 'warn', text: 'A bit short: add detail to stand out' };
    }

    return { tone: 'good', text: 'Good length' };
});

const percent = computed(() => Math.min(100, Math.round((props.length / props.limit) * 100)));

const barClass = computed(
    () =>
        ({
            good: 'bg-green-600',
            warn: 'bg-amber-500',
            bad: 'bg-red-600',
            muted: 'bg-muted-foreground/30',
        })[state.value.tone],
);
</script>

<template>
    <div class="space-y-1" aria-live="polite">
        <div class="h-1 overflow-hidden rounded-full bg-muted" aria-hidden="true">
            <div class="h-full transition-all" :class="barClass" :style="{ width: `${usingFallback ? 100 : percent}%` }" />
        </div>
        <div class="flex justify-between gap-2 text-xs">
            <span
                :class="{
                    'text-muted-foreground': state.tone === 'muted' || state.tone === 'good',
                    'text-amber-600 dark:text-amber-400': state.tone === 'warn',
                    'text-destructive': state.tone === 'bad',
                }"
                >{{ state.text }}</span
            >
            <span class="text-muted-foreground tabular-nums">{{ length }} / {{ limit }}</span>
        </div>
    </div>
</template>
