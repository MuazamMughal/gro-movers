<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Company logo from Settings > Branding; only the mark while the sidebar is collapsed. */
const brand = computed(() => usePage().props.brand);
</script>

<template>
    <img
        :src="brand.icon"
        :alt="brand.name"
        class="hidden size-8 shrink-0 rounded-md object-contain group-data-[collapsible=icon]:block dark:bg-white dark:p-0.5"
    />

    <template v-if="brand.logo">
        <!-- Transparent on the sidebar; in dark mode a white backing keeps the navy lettering readable. -->
        <span
            class="flex h-16 w-full items-center justify-center rounded-lg px-1 group-data-[collapsible=icon]:hidden dark:bg-white dark:px-2 dark:py-1"
            :class="{ 'dark:hidden': brand.logoDark }"
        >
            <img :src="brand.logo" :alt="brand.name" class="h-full w-auto max-w-full object-contain" />
        </span>
        <span v-if="brand.logoDark" class="hidden h-16 w-full items-center justify-center px-2 py-1 group-data-[collapsible=icon]:hidden! dark:flex">
            <img :src="brand.logoDark" :alt="brand.name" class="h-full w-auto max-w-full object-contain" />
        </span>
    </template>
    <template v-else>
        <img :src="brand.icon" alt="" class="size-8 shrink-0 rounded-md object-contain group-data-[collapsible=icon]:hidden dark:bg-white dark:p-0.5" />
        <span class="ml-1 truncate text-sm leading-tight font-semibold group-data-[collapsible=icon]:hidden">{{ brand.name }}</span>
    </template>
</template>
