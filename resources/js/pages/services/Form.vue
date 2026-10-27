<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import RecordPicker from '@/components/sections/RecordPicker.vue';
import SectionBuilder from '@/components/sections/SectionBuilder.vue';
import type { SectionItem, SectionOptions, SectionSchema } from '@/components/sections/types';
import { uid } from '@/components/sections/types';
import SeoPanel from '@/components/seo/SeoPanel.vue';
import type { SeoDefaults, SeoProps } from '@/components/seo/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { slugify } from '@/lib/slug';
import { index, store, update } from '@/routes/admin/services';

type ServiceData = {
    id: number;
    title: string;
    slug: string;
    short_description: string | null;
    icon_media_id: number | null;
    image_media_id: number | null;
    hero_media_id: number | null;
    is_active: boolean;
    show_in_menu: boolean;
    show_on_home: boolean;
    path: string;
    faq_ids: number[];
};

const props = defineProps<{
    service: ServiceData | null;
    seo: SeoProps;
    sections: SectionItem[];
    sectionSchema: SectionSchema;
    sectionOptions: SectionOptions;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Services', href: index() }] },
});

const isNew = computed(() => props.service === null);
const siteUrl = computed(() => (usePage().props.seoDefaults as SeoDefaults).site_url.replace(/\/$/, ''));

const form = useForm({
    title: props.service?.title ?? '',
    slug: props.service?.slug ?? '',
    short_description: props.service?.short_description ?? '',
    icon_media_id: props.service?.icon_media_id ?? null,
    image_media_id: props.service?.image_media_id ?? null,
    hero_media_id: props.service?.hero_media_id ?? null,
    is_active: props.service?.is_active ?? true,
    show_in_menu: props.service?.show_in_menu ?? true,
    show_on_home: props.service?.show_on_home ?? true,
    faq_ids: props.service?.faq_ids ?? [],
    seo: { ...props.seo.values },
    sections: props.sections.map((s) => ({ ...s, _key: uid() })),
});

useUnsavedGuard(() => form.isDirty && !form.processing);

const slugTouched = ref(!isNew.value);
watch(
    () => form.title,
    (title) => !slugTouched.value && (form.slug = slugify(title)),
);

type Tab = 'details' | 'sections' | 'faqs' | 'seo';
const tab = ref<Tab>(isNew.value ? 'details' : 'sections');
const tabs: { key: Tab; label: string }[] = [
    { key: 'details', label: 'Details' },
    { key: 'sections', label: 'Page sections' },
    { key: 'faqs', label: 'FAQs' },
    { key: 'seo', label: 'SEO' },
];

const tabErrors = (key: Tab) =>
    Object.keys(form.errors).filter((k) => {
        if (key === 'sections') return k.startsWith('sections.');
        if (key === 'seo') return k.startsWith('seo.');
        if (key === 'faqs') return k.startsWith('faq_ids');

        return !k.startsWith('sections.') && !k.startsWith('seo.') && !k.startsWith('faq_ids');
    }).length;

function submit() {
    form.transform((data) => ({ ...data, sections: data.sections.map(({ _key, ...s }) => s) }));

    const options = {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: () => {
            const first = tabs.find((t) => tabErrors(t.key) > 0);
            if (first) tab.value = first.key;
        },
    };

    if (props.service) {
        form.put(update(props.service.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

const title = computed(() => (isNew.value ? 'New service' : form.title || 'Untitled service'));
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" /> All services
                </Link>
                <div class="flex flex-wrap items-center gap-2">
                    <Heading :title="title" class="mb-0!" />
                    <Badge :variant="form.is_active ? 'default' : 'outline'">{{ form.is_active ? 'Active' : 'Inactive' }}</Badge>
                    <Badge v-if="!form.seo.robots_index" variant="destructive">NOINDEX</Badge>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button v-if="service && service.is_active" variant="ghost" as-child>
                    <a :href="service.path" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a>
                </Button>
                <Button type="button" :disabled="form.processing" @click="submit">{{ isNew ? 'Create service' : 'Save' }}</Button>
            </div>
        </div>

        <div class="flex gap-1 overflow-x-auto border-b" role="tablist" aria-label="Service editor">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium"
                :class="tab === t.key ? 'border-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                @click="tab = t.key"
            >
                {{ t.label }}
                <Badge v-if="tabErrors(t.key)" variant="destructive" class="px-1.5 text-[10px]">{{ tabErrors(t.key) }}</Badge>
            </button>
        </div>

        <!-- Details -->
        <section v-show="tab === 'details'" class="grid max-w-4xl gap-8 lg:grid-cols-2" role="tabpanel">
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label for="service-title">Service name <span class="text-destructive">*</span></Label>
                    <Input id="service-title" v-model="form.title" maxlength="120" placeholder="Local Moving" :aria-invalid="!!form.errors.title" />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="service-slug">URL</Label>
                    <div class="flex items-center rounded-md border shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50">
                        <span class="pl-3 font-mono text-sm text-muted-foreground">{{ siteUrl }}/services/</span>
                        <input
                            id="service-slug"
                            v-model="form.slug"
                            class="h-9 min-w-0 flex-1 bg-transparent pr-3 font-mono text-sm outline-none"
                            :aria-invalid="!!form.errors.slug"
                            @input="slugTouched = true"
                            @blur="form.slug = slugify(form.slug)"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">Changing it later redirects the old URL (and its city pages) automatically.</p>
                    <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="service-short">Short description</Label>
                    <Textarea id="service-short" v-model="form.short_description" rows="3" maxlength="500" />
                    <p class="text-xs text-muted-foreground">Shown on service cards and used as the default meta description. {{ form.short_description?.length ?? 0 }}/500</p>
                    <p v-if="form.errors.short_description" class="text-sm text-destructive">{{ form.errors.short_description }}</p>
                </div>

                <fieldset class="space-y-3">
                    <legend class="mb-1 text-sm font-medium">Visibility</legend>
                    <label class="flex items-center gap-3 text-sm"><Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" /> Active (page is on the website)</label>
                    <label class="flex items-center gap-3 text-sm"><Checkbox :model-value="form.show_in_menu" @update:model-value="(v) => (form.show_in_menu = v === true)" /> Show in the Services menu</label>
                    <label class="flex items-center gap-3 text-sm"><Checkbox :model-value="form.show_on_home" @update:model-value="(v) => (form.show_on_home = v === true)" /> Show on the home page services grid</label>
                </fieldset>
            </div>

            <div class="space-y-6">
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Card image</span>
                    <MediaPicker v-model="form.image_media_id" preset="card" label="Service card image" />
                    <p class="text-xs text-muted-foreground">Used on service cards across the site.</p>
                </div>
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Hero image</span>
                    <MediaPicker v-model="form.hero_media_id" preset="hero" label="Service hero image" />
                    <p class="text-xs text-muted-foreground">Background of the page's top banner when the page has no Hero section of its own.</p>
                </div>
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Icon (optional)</span>
                    <MediaPicker v-model="form.icon_media_id" preset="square" label="Service icon" />
                </div>
            </div>
        </section>

        <!-- Sections -->
        <section v-show="tab === 'sections'" role="tabpanel" class="space-y-4">
            <p class="max-w-3xl rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                If you add no Hero, the page starts with an automatic hero (service name, short description, hero image).
                FAQs from the FAQs tab and a closing "Call Now / Get a Free Quote" banner are added automatically unless you add those sections yourself.
                Tokens like {company} and {phone} work in any text.
            </p>
            <SectionBuilder v-model="form.sections" :schema="sectionSchema" :options="sectionOptions" :errors="form.errors as Record<string, string>" />
        </section>

        <!-- FAQs -->
        <section v-show="tab === 'faqs'" class="max-w-2xl space-y-3" role="tabpanel">
            <Heading variant="small" title="Frequently asked questions" description="Pick questions from the FAQ bank. They appear on this service page and in Google's FAQ results." />
            <RecordPicker id="service-faqs" v-model="form.faq_ids" :options="sectionOptions.faqs" noun="FAQs" />
            <p v-if="!sectionOptions.faqs.length" class="text-sm text-muted-foreground">The FAQ bank is empty. FAQs can be written in the FAQs module.</p>
            <p v-if="form.errors.faq_ids" class="text-sm text-destructive">{{ form.errors.faq_ids }}</p>
        </section>

        <!-- SEO -->
        <section v-show="tab === 'seo'" class="max-w-3xl" role="tabpanel">
            <SeoPanel
                v-model="form.seo"
                :fallbacks="{ ...seo.fallbacks, description: form.short_description || seo.fallbacks.description, image_id: form.hero_media_id ?? form.image_media_id ?? seo.fallbacks.image_id }"
                :url="service ? seo.url : `${siteUrl}/services/${form.slug || 'your-service'}`"
                :record-title="form.title"
                :errors="form.errors as Record<string, string>"
            />
        </section>
    </div>
</template>
