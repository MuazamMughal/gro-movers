<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { LinkValue } from './types';

const props = defineProps<{ id: string; errors?: Record<string, string | undefined>; errorKey: string }>();
const model = defineModel<LinkValue>({ default: null });

const value = computed(() => model.value ?? { label: '', url: '', new_tab: false });

const update = (patch: Partial<NonNullable<LinkValue>>) => {
    const next = { ...value.value, ...patch };
    model.value = !next.label && !next.url ? null : next;
};

// Placeholder bound from script: a literal "https://" in a template attribute breaks vue-tsc.
const URL_PLACEHOLDER = '/get-a-quote, tel:+1214…, or https://…';
</script>

<template>
    <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
        <Input
            :id="`${id}-label`"
            :model-value="value.label"
            placeholder="Button text"
            aria-label="Link text"
            :aria-invalid="!!props.errors?.[`${errorKey}.label`]"
            @update:model-value="(v: string | number) => update({ label: String(v) })"
        />
        <Input
            :id="`${id}-url`"
            :model-value="value.url"
            :placeholder="URL_PLACEHOLDER"
            aria-label="Link URL"
            :aria-invalid="!!props.errors?.[`${errorKey}.url`]"
            @update:model-value="(v: string | number) => update({ url: String(v) })"
        />
        <label v-if="value.url.startsWith('http')" class="flex items-center gap-2 text-xs text-muted-foreground sm:col-span-2">
            <Checkbox :model-value="!!value.new_tab" @update:model-value="(v) => update({ new_tab: v === true })" />
            Open in a new tab
        </label>
        <p v-if="props.errors?.[`${errorKey}.label`]" class="text-sm text-destructive sm:col-span-2">{{ props.errors[`${errorKey}.label`] }}</p>
        <p v-if="props.errors?.[`${errorKey}.url`]" class="text-sm text-destructive sm:col-span-2">{{ props.errors[`${errorKey}.url`] }}</p>
    </div>
</template>
