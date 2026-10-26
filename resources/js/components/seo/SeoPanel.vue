<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, EyeOff, Monitor, Smartphone } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import type { MediaItem } from '@/components/media/types';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { request } from '@/lib/http';
import { show as showMedia } from '@/routes/admin/media';
import LengthMeter from './LengthMeter.vue';
import type { SeoDefaults, SeoProps, SeoValues } from './types';

const props = withDefaults(
    defineProps<{
        fallbacks: SeoProps['fallbacks'];
        /** Absolute public URL (null for unsaved records). */
        url: SeoProps['url'];
        /**
         * The record's current title in the parent form, so the fallback title
         * updates live while typing. The site title suffix is appended.
         */
        recordTitle?: string | null;
        /** Validation errors keyed like "seo.meta_title". */
        errors?: Record<string, string | undefined>;
        errorPrefix?: string;
    }>(),
    { recordTitle: null, errors: () => ({}), errorPrefix: 'seo' },
);

const model = defineModel<SeoValues>({ required: true });

const defaults = computed(() => usePage().props.seoDefaults as SeoDefaults);

const set = <K extends keyof SeoValues>(key: K, value: SeoValues[K]) => (model.value = { ...model.value, [key]: value });
const str = (v: string | number) => (String(v).trim() === '' ? null : String(v));
const error = (field: keyof SeoValues) => props.errors[`${props.errorPrefix}.${field}`];

// ---- Effective values (what Google will actually see) ---------------------
const fallbackTitle = computed(() => {
    if (props.recordTitle !== null) {
        return props.recordTitle.trim() ? `${props.recordTitle.trim()} ${defaults.value.title_suffix}`.trim() : '';
    }

    return props.fallbacks.title ?? '';
});
const fallbackDescription = computed(() => props.fallbacks.description ?? defaults.value.default_description ?? '');

const title = computed(() => model.value.meta_title?.trim() || fallbackTitle.value);
const description = computed(() => model.value.meta_description?.trim() || fallbackDescription.value);
const ogTitle = computed(() => {
    if (model.value.og_title?.trim()) return model.value.og_title.trim();

    const suffix = defaults.value.title_suffix;

    return suffix && title.value.endsWith(suffix) ? title.value.slice(0, -suffix.length).trim() : title.value;
});
const ogDescription = computed(() => model.value.og_description?.trim() || description.value);

// ---- Google result preview ------------------------------------------------
const devices = ['desktop', 'mobile'] as const;
const device = ref<(typeof devices)[number]>('desktop');

const truncate = (text: string, max: number) => (text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text);

const displayUrl = computed(() => {
    const url = props.url ?? defaults.value.site_url;

    try {
        const u = new URL(model.value.canonical_url?.startsWith('http') ? model.value.canonical_url : url);
        const parts = u.pathname.split('/').filter(Boolean);

        return { host: u.host, crumbs: parts };
    } catch {
        return { host: url, crumbs: [] as string[] };
    }
});

// ---- Social preview image --------------------------------------------------
const imageId = computed(() => model.value.og_image_id ?? props.fallbacks.image_id ?? defaults.value.default_image_id);
const image = ref<MediaItem | null>(null);

watch(
    imageId,
    async (id) => {
        image.value = null;

        if (id) {
            try {
                image.value = (await request<{ media: MediaItem }>(showMedia(id).url)).media;
            } catch {
                image.value = null;
            }
        }
    },
    { immediate: true },
);

// ---- Indexing guard --------------------------------------------------------
const confirmNoindex = ref(false);

function toggleIndex(checked: boolean | 'indeterminate') {
    if (checked === true) {
        set('robots_index', true);
    } else {
        // Hiding a page from Google is rarely intended: always ask first. Open after
        // the checkbox click has finished, or the dialog sees it as an outside click and closes.
        setTimeout(() => (confirmNoindex.value = true));
    }
}

const advancedOpen = ref(!!(model.value.canonical_url || model.value.og_title || model.value.og_description));
</script>

<template>
    <div class="space-y-8">
        <div
            v-if="!model.robots_index"
            class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200"
            role="status"
        >
            <EyeOff class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div>
                <p class="font-semibold">Hidden from Google (noindex)</p>
                <p>This page will not appear in search results and is left out of the sitemap.</p>
            </div>
        </div>

        <!-- Google preview -->
        <section class="space-y-3" aria-labelledby="serp-heading">
            <div class="flex items-center justify-between">
                <h3 id="serp-heading" class="text-sm font-semibold">Google preview</h3>
                <div class="flex rounded-md border p-0.5" role="group" aria-label="Preview device">
                    <button
                        v-for="d in devices"
                        :key="d"
                        type="button"
                        class="rounded px-2 py-1"
                        :class="device === d ? 'bg-muted' : 'text-muted-foreground'"
                        :aria-pressed="device === d"
                        :aria-label="`${d} preview`"
                        @click="device = d"
                    >
                        <Monitor v-if="d === 'desktop'" class="size-4" />
                        <Smartphone v-else class="size-4" />
                    </button>
                </div>
            </div>

            <div
                class="rounded-lg border bg-white p-4 font-[Arial,sans-serif] text-left text-[#202124]"
                :class="device === 'mobile' ? 'max-w-sm' : 'max-w-2xl'"
            >
                <div class="flex items-center gap-3">
                    <span class="flex size-7 items-center justify-center rounded-full border border-[#dadce0] bg-[#f1f3f4] text-xs font-bold text-[#5f6368]">
                        {{ defaults.site_name.charAt(0) }}
                    </span>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm">{{ defaults.site_name }}</p>
                        <p class="truncate text-xs text-[#4d5156]">
                            https://{{ displayUrl.host }}<template v-for="c in displayUrl.crumbs" :key="c"> › {{ c }}</template>
                        </p>
                    </div>
                </div>
                <p
                    class="mt-2 leading-snug text-[#1a0dab]"
                    :class="device === 'mobile' ? 'text-base' : 'text-xl'"
                >
                    {{ title ? truncate(title, device === 'mobile' ? 78 : defaults.title_limit) : 'Add a title' }}
                </p>
                <p class="mt-1 text-sm leading-normal text-[#4d5156]">
                    {{ description ? truncate(description, defaults.description_limit) : 'Add a meta description to control the text shown here.' }}
                </p>
            </div>
        </section>

        <!-- Title & description -->
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="seo-meta-title">Meta title</Label>
                <Input
                    id="seo-meta-title"
                    :model-value="model.meta_title ?? ''"
                    :placeholder="fallbackTitle"
                    :aria-invalid="!!error('meta_title')"
                    aria-describedby="seo-meta-title-help"
                    @update:model-value="(v) => set('meta_title', str(v))"
                />
                <LengthMeter
                    :length="title.length"
                    :limit="defaults.title_limit"
                    :min="30"
                    :using-fallback="!model.meta_title"
                />
                <p id="seo-meta-title-help" class="text-xs text-muted-foreground">
                    Shown as the blue link in Google. Leave empty to use the page title plus "{{ defaults.title_suffix }}".
                    Must be unique across the site.
                </p>
                <p v-if="error('meta_title')" class="text-sm text-destructive">{{ error('meta_title') }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="seo-meta-description">Meta description</Label>
                <Textarea
                    id="seo-meta-description"
                    :model-value="model.meta_description ?? ''"
                    :placeholder="fallbackDescription"
                    rows="3"
                    :aria-invalid="!!error('meta_description')"
                    @update:model-value="(v) => set('meta_description', str(v))"
                />
                <LengthMeter
                    :length="description.length"
                    :limit="defaults.description_limit"
                    :min="70"
                    :using-fallback="!model.meta_description"
                />
                <p class="text-xs text-muted-foreground">
                    The grey text under the link. Aim for 120–160 characters with the service, the city and a reason to click.
                </p>
                <p v-if="error('meta_description')" class="text-sm text-destructive">{{ error('meta_description') }}</p>
            </div>
        </div>

        <!-- Social -->
        <section class="space-y-4 border-t pt-6" aria-labelledby="social-heading">
            <div>
                <h3 id="social-heading" class="text-sm font-semibold">Social sharing</h3>
                <p class="text-xs text-muted-foreground">How the link looks on Facebook, LinkedIn, WhatsApp and X.</p>
            </div>

            <div class="max-w-md overflow-hidden rounded-lg border bg-card">
                <div class="aspect-[1.91/1] bg-muted">
                    <img v-if="image" :src="image.url" :alt="image.alt ?? ''" class="size-full object-cover" />
                    <div v-else class="flex size-full items-center justify-center text-xs text-muted-foreground">No share image</div>
                </div>
                <div class="space-y-0.5 p-3">
                    <p class="text-xs text-muted-foreground uppercase">{{ displayUrl.host }}</p>
                    <p class="line-clamp-2 text-sm font-semibold">{{ ogTitle || 'Add a title' }}</p>
                    <p class="line-clamp-2 text-xs text-muted-foreground">{{ ogDescription }}</p>
                </div>
            </div>

            <div class="grid gap-2">
                <span class="text-sm font-medium">Share image</span>
                <MediaPicker
                    :model-value="model.og_image_id"
                    preset="blog"
                    label="Social share image"
                    @update:model-value="(id) => set('og_image_id', id)"
                />
                <p class="text-xs text-muted-foreground">
                    1200 × 630 px. Leave empty to use this page's main image, or the site default.
                </p>
                <p v-if="error('og_image_id')" class="text-sm text-destructive">{{ error('og_image_id') }}</p>
            </div>
        </section>

        <!-- Advanced -->
        <section class="border-t pt-6">
            <button
                type="button"
                class="flex w-full items-center justify-between text-sm font-semibold"
                :aria-expanded="advancedOpen"
                aria-controls="seo-advanced"
                @click="advancedOpen = !advancedOpen"
            >
                Advanced
                <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': advancedOpen }" />
            </button>

            <div v-show="advancedOpen" id="seo-advanced" class="mt-4 space-y-6">
                <div class="grid gap-2">
                    <Label for="seo-og-title">Social title</Label>
                    <Input
                        id="seo-og-title"
                        :model-value="model.og_title ?? ''"
                        :placeholder="ogTitle"
                        @update:model-value="(v) => set('og_title', str(v))"
                    />
                    <p v-if="error('og_title')" class="text-sm text-destructive">{{ error('og_title') }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="seo-og-description">Social description</Label>
                    <Textarea
                        id="seo-og-description"
                        :model-value="model.og_description ?? ''"
                        :placeholder="description"
                        rows="2"
                        @update:model-value="(v) => set('og_description', str(v))"
                    />
                    <p v-if="error('og_description')" class="text-sm text-destructive">{{ error('og_description') }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="seo-canonical">Canonical URL</Label>
                    <Input
                        id="seo-canonical"
                        :model-value="model.canonical_url ?? ''"
                        :placeholder="url ?? 'This page'"
                        :aria-invalid="!!error('canonical_url')"
                        @update:model-value="(v) => set('canonical_url', str(v))"
                    />
                    <p class="text-xs text-muted-foreground">
                        Only set this when another page is the main version of this content. Leave empty in almost all cases.
                    </p>
                    <p v-if="error('canonical_url')" class="text-sm text-destructive">{{ error('canonical_url') }}</p>
                </div>

                <div class="space-y-3">
                    <label class="flex items-center gap-3 text-sm font-medium">
                        <Checkbox :model-value="model.robots_index" @update:model-value="toggleIndex" />
                        Show this page in Google (index)
                        <Badge v-if="!model.robots_index" variant="destructive">NOINDEX</Badge>
                    </label>
                    <label class="flex items-center gap-3 text-sm font-medium">
                        <Checkbox
                            :model-value="model.robots_follow"
                            @update:model-value="(v) => set('robots_follow', v === true)"
                        />
                        Let Google follow links on this page (follow)
                    </label>
                </div>
            </div>
        </section>
    </div>

    <ConfirmDialog
        v-model:open="confirmNoindex"
        title="Hide this page from Google?"
        description="Noindex removes the page from search results and the sitemap. Only do this for pages like thank-you or private pages, never for services, areas or blog posts you want to rank."
        confirm-label="Yes, hide from Google"
        destructive
        @confirm="
            () => {
                set('robots_index', false);
                confirmNoindex = false;
            }
        "
    />
</template>
