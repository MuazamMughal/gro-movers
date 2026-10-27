<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Eye } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import RecordPicker from '@/components/sections/RecordPicker.vue';
import SectionBuilder from '@/components/sections/SectionBuilder.vue';
import type { SectionItem, SectionOptions, SectionSchema } from '@/components/sections/types';
import { uid } from '@/components/sections/types';
import SeoPanel from '@/components/seo/SeoPanel.vue';
import type { SeoDefaults, SeoProps } from '@/components/seo/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { openPreview } from '@/lib/preview';
import { slugify } from '@/lib/slug';
import { index, store, update } from '@/routes/admin/pages';
import { page as previewPage } from '@/routes/admin/preview';

type PageData = {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    is_system: boolean;
    system_label: string | null;
    slug_locked: boolean;
    path: string | null;
    published_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    page: PageData | null;
    faqIds: number[];
    seo: SeoProps;
    sections: SectionItem[];
    sectionSchema: SectionSchema;
    sectionOptions: SectionOptions;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pages', href: index() }] },
});

const isNew = computed(() => props.page === null);
const seoDefaults = computed(() => usePage().props.seoDefaults as SeoDefaults);

// Client keys are added before the form snapshots its defaults, so the builder
// adding them does not count as an unsaved change.
const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    status: props.page?.status ?? 'draft',
    faq_ids: props.faqIds,
    seo: { ...props.seo.values },
    sections: props.sections.map((s) => ({ ...s, _key: uid() })),
});

useUnsavedGuard(() => form.isDirty && !form.processing);

// ---- Slug follows the title until edited by hand (new pages only) -----------
const slugTouched = ref(!isNew.value);

watch(
    () => form.title,
    (title) => {
        if (!slugTouched.value) {
            form.slug = slugify(title);
        }
    },
);

const siteUrl = computed(() => seoDefaults.value.site_url.replace(/\/$/, ''));
const publicPath = computed(() => (props.page?.slug_locked ? props.page.path : `/${form.slug || 'your-page'}`));

// ---- Tabs with error counts ---------------------------------------------------
type Tab = 'content' | 'sections' | 'faqs' | 'seo';
const tab = ref<Tab>('sections');
const tabs: { key: Tab; label: string }[] = [
    { key: 'content', label: 'Page details' },
    { key: 'sections', label: 'Sections' },
    { key: 'faqs', label: 'FAQs' },
    { key: 'seo', label: 'SEO' },
];

const errorKeys = computed(() => Object.keys(form.errors));
const tabErrors = (key: Tab) =>
    errorKeys.value.filter((k) =>
        key === 'sections'
            ? k.startsWith('sections.')
            : key === 'seo'
              ? k.startsWith('seo.')
              : key === 'faqs'
                ? k.startsWith('faq_ids')
                : !k.startsWith('sections.') && !k.startsWith('seo.') && !k.startsWith('faq_ids'),
    ).length;

// ---- Save / publish -------------------------------------------------------------
function submit(status: 'draft' | 'published') {
    form.status = status;

    form.transform((data) => ({
        ...data,
        slug: props.page?.slug_locked ? undefined : data.slug,
        sections: data.sections.map(({ _key, ...s }) => s),
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
        },
        onError: () => {
            const first = tabs.find((t) => tabErrors(t.key) > 0);

            if (first) tab.value = first.key;
        },
    };

    if (props.page) {
        form.put(update(props.page.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

function preview() {
    openPreview(previewPage().url, {
        id: props.page?.id ?? null,
        title: form.title,
        slug: form.slug,
        seo: form.seo,
        sections: form.sections.map(({ _key, ...s }) => s),
    });
}

const isPublished = computed(() => props.page?.status === 'published');
const title = computed(() => (isNew.value ? 'New page' : form.title || 'Untitled page'));
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Header + actions -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" /> All pages
                </Link>
                <div class="flex flex-wrap items-center gap-2">
                    <Heading :title="title" class="mb-0!" />
                    <Badge v-if="page?.is_system" variant="secondary">System page · {{ page.system_label }}</Badge>
                    <Badge :variant="isPublished ? 'default' : 'outline'">{{ isPublished ? 'Published' : 'Draft' }}</Badge>
                    <Badge v-if="!form.seo.robots_index" variant="destructive">NOINDEX</Badge>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button v-if="isPublished && page?.path" variant="ghost" as-child>
                    <a :href="page.path" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a>
                </Button>
                <Button type="button" variant="outline" @click="preview"><Eye class="size-4" /> Preview</Button>
                <template v-if="isPublished">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="submit('draft')">Unpublish</Button>
                    <Button type="button" :disabled="form.processing" @click="submit('published')">Update</Button>
                </template>
                <template v-else>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="submit('draft')">Save draft</Button>
                    <Button type="button" :disabled="form.processing" @click="submit('published')">Publish</Button>
                </template>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 border-b" role="tablist" aria-label="Page editor">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium"
                :class="tab === t.key ? 'border-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                @click="tab = t.key"
            >
                {{ t.label }}
                <Badge v-if="tabErrors(t.key)" variant="destructive" class="px-1.5 text-[10px]">{{ tabErrors(t.key) }}</Badge>
            </button>
        </div>

        <!-- Page details -->
        <section v-show="tab === 'content'" class="max-w-2xl space-y-6" role="tabpanel">
            <div class="grid gap-2">
                <Label for="page-title">Title <span class="text-destructive">*</span></Label>
                <Input id="page-title" v-model="form.title" maxlength="150" :aria-invalid="!!form.errors.title" />
                <p class="text-xs text-muted-foreground">Used in the admin, breadcrumbs and as the default SEO title.</p>
                <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="page-slug">URL</Label>
                <template v-if="page?.slug_locked">
                    <p class="rounded-md border bg-muted/40 px-3 py-2 font-mono text-sm">{{ siteUrl }}{{ page.path }}</p>
                    <p class="text-xs text-muted-foreground">This page's URL is fixed by the website.</p>
                </template>
                <template v-else>
                    <div class="flex items-center rounded-md border shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50">
                        <span class="pl-3 font-mono text-sm text-muted-foreground">{{ siteUrl }}/</span>
                        <input
                            id="page-slug"
                            v-model="form.slug"
                            class="h-9 flex-1 bg-transparent pr-3 font-mono text-sm outline-none"
                            :aria-invalid="!!form.errors.slug"
                            @input="slugTouched = true"
                            @blur="form.slug = slugify(form.slug)"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Short, lowercase, with hyphens. Changing it later creates a redirect from the old URL automatically.
                    </p>
                    <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                </template>
            </div>

            <div class="rounded-lg border bg-muted/30 p-4 text-sm">
                <p class="font-medium">Status: {{ isPublished ? 'Published' : 'Draft' }}</p>
                <p class="text-muted-foreground">
                    {{ isPublished ? 'Visible on the website.' : 'Not visible on the website until you publish it. Use Preview to check it first.' }}
                </p>
            </div>
        </section>

        <!-- Sections -->
        <section v-show="tab === 'sections'" role="tabpanel">
            <SectionBuilder
                v-model="form.sections"
                :schema="sectionSchema"
                :options="sectionOptions"
                :errors="form.errors as Record<string, string>"
            />
        </section>

        <!-- SEO -->
        <section v-show="tab === 'faqs'" class="max-w-2xl space-y-3" role="tabpanel">
            <Heading variant="small" title="Frequently asked questions" description="Pick questions from the FAQ bank. They appear in an FAQ section near the end of the page (or in your own FAQ section set to “FAQs attached to this page”)." />
            <RecordPicker id="page-faqs" v-model="form.faq_ids" :options="sectionOptions.faqs" noun="FAQs" />
            <p v-if="!sectionOptions.faqs.length" class="text-sm text-muted-foreground">The FAQ bank is empty. Write FAQs in the FAQs module first.</p>
            <p v-if="form.errors.faq_ids" class="text-sm text-destructive">{{ form.errors.faq_ids }}</p>
        </section>

        <section v-show="tab === 'seo'" class="max-w-3xl" role="tabpanel">
            <SeoPanel
                v-model="form.seo"
                :fallbacks="seo.fallbacks"
                :url="page ? seo.url : `${siteUrl}${publicPath}`"
                :record-title="page?.system_label === 'Home' ? null : form.title"
                :errors="form.errors as Record<string, string>"
            />
        </section>
    </div>
</template>
