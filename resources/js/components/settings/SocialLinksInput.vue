<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    networks: Record<string, string>;
    errors: Record<string, string>;
    errorPrefix: string;
}>();

const model = defineModel<Record<string, string | null> | null>();

// A literal "https://" in a static template attribute trips up vue-tsc's scope analysis.
const URL_PLACEHOLDER = 'https://';

const entries = computed(() => Object.entries(props.networks));

const update = (network: string, url: string | number) =>
    (model.value = { ...(model.value ?? {}), [network]: String(url) || null });
</script>

<template>
    <div class="grid gap-2 sm:grid-cols-2">
        <div v-for="[network, label] in entries" :key="network" class="grid gap-1">
            <span class="text-xs text-muted-foreground">{{ label }}</span>
            <Input
                :model-value="model?.[network] ?? ''"
                type="url"
                :placeholder="URL_PLACEHOLDER"
                :aria-label="`${label} URL`"
                :aria-invalid="!!errors[`${errorPrefix}.${network}`]"
                @update:model-value="(v: string | number) => update(network, v)"
            />
            <p v-if="errors[`${errorPrefix}.${network}`]" class="text-xs text-destructive">
                {{ errors[`${errorPrefix}.${network}`] }}
            </p>
        </div>
    </div>
</template>
