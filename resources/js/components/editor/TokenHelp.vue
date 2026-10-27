<script setup lang="ts">
/** Explains content tokens with example values for the current city/service. */
defineProps<{ tokens: Record<string, string> }>();
</script>

<template>
    <div class="rounded-lg border bg-muted/30 p-3 text-sm">
        <p class="font-medium">Tokens you can use in any text</p>
        <p class="text-muted-foreground">They are replaced on each page. Example values:</p>
        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 font-mono text-xs">
            <template v-for="(value, key) in tokens" :key="key">
                <dt class="text-primary">{{ '{' + key + '}' }}</dt>
                <dd class="truncate text-muted-foreground">{{ value }}</dd>
            </template>
        </dl>
    </div>
</template>
