<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, X } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import EditorTabs from '@/components/editor/EditorTabs.vue';
import Heading from '@/components/Heading.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import RecordPicker from '@/components/sections/RecordPicker.vue';
import SectionBuilder from '@/components/sections/SectionBuilder.vue';
import type { PickerOption, SectionItem, SectionOptions, SectionSchema } from '@/components/sections/types';
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
import { index, store, update } from '@/routes/admin/areas';

type AreaData = {
    id: number;
    city: string;
    state_name: string;
    state_code: string;
    slug: string;
    intro: string | null;
    hero_media_id: number | null;
    image_media_id: number | null;
    lat: string | null;
    lng: string | null;
    zip_codes: string[];
    map_embed: string | null;
    is_active: boolean;
    show_in_menu: boolean;
    service_ids: number[];
    neighbor_ids: number[];
    faq_ids: number[];
    path: string;
};

const props = defineProps<{
    area: AreaData | null;
    serviceOptions: PickerOption[];
    areaOptions: PickerOption[];
    seo: SeoProps;
    sections: SectionItem[];
    sectionSchema: SectionSchema;
    sectionOptions: SectionOptions;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Service Areas', href: index() }] },
});

const isNew = computed(() => props.area === null);
const siteUrl = computed(() => (usePage().props.seoDefaults as SeoDefaults).site_url.replace(/\/$/, ''));

const form = useForm({
    city: props.area?.city ?? '',
    state_name: props.area?.state_name ?? 'Texas',
    state_code: props.area?.state_code ?? 'TX',
    slug: props.area?.slug ?? '',
    intro: props.area?.intro ?? '',
    hero_media_id: props.area?.hero_media_id ?? null,
    image_media_id: props.area?.image_media_id ?? null,
    lat: props.area?.lat ?? '',
    lng: props.area?.lng ?? '',
    zip_codes: props.area?.zip_codes ?? [],
    map_embed: props.area?.map_embed ?? '',
    is_active: props.area?.is_active ?? true,
    show_in_menu: props.area?.show_in_menu ?? true,
    // New areas offer every active service by default.
    service_ids: props.area?.service_ids ?? props.serviceOptions.filter((s) => !s.hint).map((s) => s.id),
    neighbor_ids: props.area?.neighbor_ids ?? [],
    faq_ids: props.area?.faq_ids ?? [],
    seo: { ...props.seo.values },
    sections: props.sections.map((s) => ({ ...s, _key: uid() })),
});

useUnsavedGuard(() => form.isDirty && !form.processing);

// "Plano" + "TX" → "plano-tx" until the URL is edited by hand.
const slugTouched = ref(!isNew.value);
watch(
    () => [form.city, form.state_code],
    () => !slugTouched.value && (form.slug = slugify(`${form.city} ${form.state_code}`)),
);

const nameFields = ['city', 'state_name', 'state_code'] as const;

const name = computed(() => (form.city ? `${form.city}, ${form.state_code.toUpperCase()}` : ''));

// ---- ZIP code tags --------------------------------------------------------------
const zipInput = ref('');
function addZips() {
    const found = zipInput.value.match(/\d{5}/g) ?? [];
    form.zip_codes = [...new Set([...form.zip_codes, ...found])];
    zipInput.value = '';
}

// ---- Services offered ----------------------------------------------------------------
const toggleService = (id: number, on: boolean) =>
    (form.service_ids = on ? [...form.service_ids, id] : form.service_ids.filter((x) => x !== id));

type Tab = 'details' | 'location' | 'services' | 'sections' | 'faqs' | 'seo';
const tab = ref<Tab>(isNew.value ? 'details' : 'sections');
const tabs: { key: Tab; label: string; match?: (k: string) => boolean }[] = [
    { key: 'details', label: 'Details' },
    { key: 'location', label: 'Location', match: (k) => ['lat', 'lng', 'map_embed'].includes(k) || k.startsWith('zip_codes') },
    { key: 'services', label: 'Services & nearby', match: (k) => k.startsWith('service_ids') || k.startsWith('neighbor_ids') },
    { key: 'sections', label: 'Page sections', match: (k) => k.startsWith('sections.') },
    { key: 'faqs', label: 'FAQs', match: (k) => k.startsWith('faq_ids') },
    { key: 'seo', label: 'SEO', match: (k) => k.startsWith('seo.') },
];
const tabBar = useTemplateRef('tabBar');

function submit() {
    form.transform((data) => ({
        ...data,
        lat: data.lat === '' ? null : data.lat,
        lng: data.lng === '' ? null : data.lng,
        map_embed: data.map_embed || null,
        sections: data.sections.map(({ _key, ...s }) => s),
    }));

    const options = { preserveScroll: true, onSuccess: () => form.defaults(), onError: () => tabBar.value?.focusErrors() };

    if (props.area) {
        form.put(update(props.area.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

const title = computed(() => (isNew.value ? 'New service area' : name.value || 'Untitled area'));
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft class="size-4" /> All areas</Link>
                <div class="flex flex-wrap items-center gap-2">
                    <Heading :title="title" class="mb-0!" />
                    <Badge :variant="form.is_active ? 'default' : 'outline'">{{ form.is_active ? 'Active' : 'Inactive' }}</Badge>
                    <Badge v-if="!form.seo.robots_index" variant="destructive">NOINDEX</Badge>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button v-if="area && area.is_active" variant="ghost" as-child><a :href="area.path" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a></Button>
                <Button type="button" :disabled="form.processing" @click="submit">{{ isNew ? 'Create area' : 'Save' }}</Button>
            </div>
        </div>

        <EditorTabs ref="tabBar" v-model="tab" :tabs="tabs" :errors="form.errors as Record<string, string>" label="Area editor" />

        <!-- Details -->
        <section v-show="tab === 'details'" class="grid max-w-4xl gap-8 lg:grid-cols-2" role="tabpanel">
            <div class="space-y-6">
                <div class="grid gap-2 sm:grid-cols-[1fr_9rem_5rem] sm:gap-x-3">
                    <div class="grid gap-2">
                        <Label for="area-city">City <span class="text-destructive">*</span></Label>
                        <Input id="area-city" v-model="form.city" maxlength="80" placeholder="Plano" :aria-invalid="!!form.errors.city" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="area-state">State</Label>
                        <Input id="area-state" v-model="form.state_name" maxlength="60" :aria-invalid="!!form.errors.state_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="area-code">Code</Label>
                        <Input id="area-code" v-model="form.state_code" maxlength="2" class="uppercase" :aria-invalid="!!form.errors.state_code" />
                    </div>
                    <p v-for="f in nameFields" v-show="form.errors[f]" :key="f" class="text-sm text-destructive sm:col-span-3">{{ form.errors[f] }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="area-slug">URL</Label>
                    <div class="flex items-center rounded-md border shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50">
                        <span class="pl-3 font-mono text-sm text-muted-foreground">{{ siteUrl }}/service-areas/</span>
                        <input id="area-slug" v-model="form.slug" class="h-9 min-w-0 flex-1 bg-transparent pr-3 font-mono text-sm outline-none" :aria-invalid="!!form.errors.slug" @input="slugTouched = true" @blur="form.slug = slugify(form.slug)" />
                    </div>
                    <p class="text-xs text-muted-foreground">Changing it later redirects the old city page and all its service pages.</p>
                    <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="area-intro">Intro</Label>
                    <Textarea id="area-intro" v-model="form.intro" rows="4" maxlength="1000" placeholder="Short local introduction: neighborhoods you serve, what makes moving here special…" />
                    <p class="text-xs text-muted-foreground">Shown in the city page's top banner and used as the default meta description.</p>
                </div>

                <fieldset class="space-y-3">
                    <legend class="mb-1 text-sm font-medium">Visibility</legend>
                    <label class="flex items-center gap-3 text-sm"><Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" /> Active (city pages are on the website)</label>
                    <label class="flex items-center gap-3 text-sm"><Checkbox :model-value="form.show_in_menu" @update:model-value="(v) => (form.show_in_menu = v === true)" /> Show in the Service Areas menu</label>
                </fieldset>
            </div>
            <div class="space-y-6">
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Hero image</span>
                    <MediaPicker v-model="form.hero_media_id" preset="hero" label="City hero image" />
                    <p class="text-xs text-muted-foreground">A recognizable local photo works best (skyline, neighborhood).</p>
                </div>
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Card image</span>
                    <MediaPicker v-model="form.image_media_id" preset="card" label="City card image" />
                </div>
            </div>
        </section>

        <!-- Location -->
        <section v-show="tab === 'location'" class="max-w-2xl space-y-6" role="tabpanel">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="area-lat">Latitude</Label>
                    <Input id="area-lat" v-model="form.lat" inputmode="decimal" placeholder="33.0198" :aria-invalid="!!form.errors.lat" />
                    <p v-if="form.errors.lat" class="text-sm text-destructive">{{ form.errors.lat }}</p>
                </div>
                <div class="grid gap-2">
                    <Label for="area-lng">Longitude</Label>
                    <Input id="area-lng" v-model="form.lng" inputmode="decimal" placeholder="-96.6989" :aria-invalid="!!form.errors.lng" />
                    <p v-if="form.errors.lng" class="text-sm text-destructive">{{ form.errors.lng }}</p>
                </div>
                <p class="text-xs text-muted-foreground sm:col-span-2">Right-click the city on Google Maps to copy its coordinates. Used for the map and local schema.</p>
            </div>

            <div class="grid gap-2">
                <Label for="area-zips">ZIP codes served</Label>
                <div class="flex gap-2">
                    <Input id="area-zips" v-model="zipInput" placeholder="75023, 75024, 75025" @keydown.enter.prevent="addZips" />
                    <Button type="button" variant="outline" @click="addZips">Add</Button>
                </div>
                <div v-if="form.zip_codes.length" class="flex flex-wrap gap-1.5">
                    <span v-for="zip in form.zip_codes" :key="zip" class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 font-mono text-xs">
                        {{ zip }}
                        <button type="button" :aria-label="`Remove ${zip}`" @click="form.zip_codes = form.zip_codes.filter((z) => z !== zip)"><X class="size-3" /></button>
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">Paste several at once. Listed under the map on the city page.</p>
                <p v-for="(msg, key) in form.errors" v-show="String(key).startsWith('zip_codes')" :key="key" class="text-sm text-destructive">{{ msg }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="area-map">Google Maps embed link (optional)</Label>
                <Input id="area-map" v-model="form.map_embed" :aria-invalid="!!form.errors.map_embed" />
                <p class="text-xs text-muted-foreground">Google Maps → Share → Embed a map → copy only the src="…" link. Without it, the map is centered on the coordinates.</p>
                <p v-if="form.errors.map_embed" class="text-sm text-destructive">{{ form.errors.map_embed }}</p>
            </div>
        </section>

        <!-- Services & nearby -->
        <section v-show="tab === 'services'" class="grid max-w-4xl gap-10 lg:grid-cols-2" role="tabpanel">
            <div class="space-y-3">
                <Heading variant="small" title="Services offered here" :description="`Each one gets a page like “Local Moving in ${name || 'this city'}”.`" />
                <p v-if="!serviceOptions.length" class="text-sm text-muted-foreground">Create services first.</p>
                <label v-for="service in serviceOptions" :key="service.id" class="flex items-center gap-3 text-sm">
                    <Checkbox :model-value="form.service_ids.includes(service.id)" @update:model-value="(v) => toggleService(service.id, v === true)" />
                    {{ service.label }}
                    <span v-if="service.hint" class="text-xs text-muted-foreground">({{ service.hint }})</span>
                </label>
            </div>
            <div class="space-y-3">
                <Heading variant="small" title="Nearby areas" description="Linked at the bottom of this city page (good for local SEO)." />
                <RecordPicker id="area-neighbors" v-model="form.neighbor_ids" :options="areaOptions" noun="areas" />
            </div>
        </section>

        <!-- Sections -->
        <section v-show="tab === 'sections'" role="tabpanel" class="space-y-4">
            <p class="max-w-3xl rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                Without a Hero section, the page starts with “Movers in {{ name || 'City, ST' }}”, the intro and hero image.
                The services in this city, the map, nearby areas, FAQs and a closing call to action are added automatically.
                Use {city}, {state_code} and {company} in any text.
            </p>
            <SectionBuilder v-model="form.sections" :schema="sectionSchema" :options="sectionOptions" :errors="form.errors as Record<string, string>" />
        </section>

        <!-- FAQs -->
        <section v-show="tab === 'faqs'" class="max-w-2xl space-y-3" role="tabpanel">
            <Heading variant="small" title="Frequently asked questions" description="Local questions shown on this city page." />
            <RecordPicker id="area-faqs" v-model="form.faq_ids" :options="sectionOptions.faqs" noun="FAQs" />
        </section>

        <!-- SEO -->
        <section v-show="tab === 'seo'" class="max-w-3xl" role="tabpanel">
            <SeoPanel
                v-model="form.seo"
                :fallbacks="{ ...seo.fallbacks, description: form.intro || seo.fallbacks.description, image_id: form.hero_media_id ?? form.image_media_id ?? seo.fallbacks.image_id }"
                :url="area ? seo.url : `${siteUrl}/service-areas/${form.slug || 'city-st'}`"
                :record-title="name ? `Movers in ${name}` : ''"
                :errors="form.errors as Record<string, string>"
            />
        </section>
    </div>
</template>
