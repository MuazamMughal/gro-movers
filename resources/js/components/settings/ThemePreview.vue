<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    colors: {
        primary: string;
        secondary: string;
        tertiary: string;
        muted: string;
        border: string;
    };
}>();

const isHex = (value: string) => /^#[0-9a-f]{6}$/i.test(value ?? '');

/** WCAG 2.1 relative luminance of a #RRGGBB color. */
function luminance(hex: string): number {
    const [r, g, b] = [1, 3, 5].map((i) => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a: string, b: string): number | null {
    if (!isHex(a) || !isHex(b)) {
        return null;
    }

    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (hi + 0.05) / (lo + 0.05);
}

const checks = computed(() => {
    const c = props.colors;

    return [
        { label: 'Body text (secondary on primary)', ratio: contrast(c.secondary, c.primary), min: 4.5 },
        { label: 'Button text (white on tertiary)', ratio: contrast('#FFFFFF', c.tertiary), min: 3, note: 'large bold text' },
        { label: 'Links (tertiary on primary)', ratio: contrast(c.tertiary, c.primary), min: 3, note: 'bold or underlined' },
        { label: 'Footer text (primary on secondary)', ratio: contrast(c.primary, c.secondary), min: 4.5 },
        { label: 'Text on muted sections', ratio: contrast(c.secondary, c.muted), min: 4.5 },
    ];
});

const vars = computed(() =>
    Object.fromEntries(
        Object.entries(props.colors).map(([k, v]) => [`--p-${k}`, isHex(v) ? v : '#000000']),
    ),
);
</script>

<template>
    <div class="space-y-4">
        <div
            class="overflow-hidden rounded-xl border text-[13px] shadow-sm"
            :style="vars"
            aria-label="Theme preview"
        >
            <div
                class="flex items-center justify-between px-4 py-3"
                style="background: var(--p-secondary); color: var(--p-primary)"
            >
                <span class="font-bold">Your Company</span>
                <span
                    class="rounded-md px-3 py-1.5 text-xs font-semibold"
                    style="background: var(--p-tertiary); color: #fff"
                    >Get a Free Quote</span
                >
            </div>
            <div class="space-y-2 px-4 py-5" style="background: var(--p-primary); color: var(--p-secondary)">
                <p class="text-lg font-extrabold">Stress-free moving in Dallas</p>
                <p>
                    Licensed local movers.
                    <span class="font-semibold underline" style="color: var(--p-tertiary)">See our services</span>
                </p>
                <div class="rounded-lg border p-3" style="border-color: var(--p-border)">
                    Card on a white background
                </div>
            </div>
            <div class="px-4 py-3" style="background: var(--p-muted); color: var(--p-secondary)">
                Alternating muted section
            </div>
        </div>

        <ul class="space-y-1.5 text-sm">
            <li v-for="check in checks" :key="check.label" class="flex items-center justify-between gap-3">
                <span class="text-muted-foreground">{{ check.label }}</span>
                <span
                    v-if="check.ratio !== null"
                    class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium tabular-nums"
                    :class="
                        check.ratio >= check.min
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                            : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                    "
                    :title="`Needs ${check.min}:1${check.note ? ` (${check.note})` : ''}`"
                >
                    {{ check.ratio.toFixed(2) }}:1 {{ check.ratio >= check.min ? 'Pass' : 'Low contrast' }}
                </span>
            </li>
        </ul>
        <p class="text-xs text-muted-foreground">
            WCAG AA needs 4.5:1 for normal text and 3:1 for large or bold text.
        </p>
    </div>
</template>
