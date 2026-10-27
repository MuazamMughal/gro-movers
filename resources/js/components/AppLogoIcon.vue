<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

/** The company mark: Settings > Branding favicon, or the logo mark in public/favicon.png. */
const icon = usePage().props.brand.icon;
</script>

<template>
    <img :src="icon" alt="" :class="['object-contain', className]" v-bind="$attrs" />
</template>
