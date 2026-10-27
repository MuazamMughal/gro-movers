<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import EditorTabs from '@/components/editor/EditorTabs.vue';
import TokenHelp from '@/components/editor/TokenHelp.vue';
import Heading from '@/components/Heading.vue';
import RecordPicker from '@/components/sections/RecordPicker.vue';
import SectionBuilder from '@/components/sections/SectionBuilder.vue';
import type { SectionItem, SectionOptions, SectionSchema } from '@/components/sections/types';
import { uid } from '@/components/sections/types';
import SeoPanel from '@/components/seo/SeoPanel.vue';
import type { SeoProps } from '@/components/seo/types';
import { Button } from '@/components/ui/button';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { index, template } from '@/routes/admin/area-services';
import { update as updateTemplate } from '@/routes/admin/area-services/template';
import { index as areasIndex } from '@/routes/admin/areas';

const props = defineProps<{
    faq_ids: number[];
    seo: SeoProps;
    tokens: Record<string, string>;
    usage: number;
    sections: SectionItem[];
    sectionSchema: SectionSchema;
    sectionOptions: SectionOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Service Areas', href: areasIndex() },
            { title: 'City × service pages', href: index() },
            { title: 'Template', href: template() },
        ],
    },
});

const form = useForm({
    faq_ids: props.faq_ids,
    seo: { ...props.seo.values },
    sections: props.sections.map((s) => ({ ...s, _key: uid() })),
});

useUnsavedGuard(() => form.isDirty && !form.processing);

type Tab = 'sections' | 'faqs' | 'seo';
const tab = ref<Tab>('sections');
const tabs: { key: Tab; label: string; match?: (k: string) => boolean }[] = [
    { key: 'sections', label: 'Page sections', match: (k) => k.startsWith('sections.') },
    { key: 'faqs', label: 'FAQs', match: (k) => k.startsWith('faq_ids') },
    { key: 'seo', label: 'SEO', match: (k) => k.startsWith('seo.') },
];
const tabBar = useTemplateRef('tabBar');

const submit = () =>
    form
        .transform((data) => ({ ...data, sections: data.sections.map(({ _key, ...s }) => s) }))
        .put(updateTemplate().url, { preserveScroll: true, onSuccess: () => form.defaults(), onError: () => tabBar.value?.focusErrors() });
</script>

<template>
    <Head title="City × service template" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft class="size-4" /> City × service pages</Link>
                <Heading title="Shared city × service template" :description="`Used by ${usage} ${usage === 1 ? 'page' : 'pages'}. Write it once with tokens; every city and service fills in its own name.`" class="mb-0!" />
            </div>
            <div class="flex items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button type="button" :disabled="form.processing" @click="submit">Save template</Button>
            </div>
        </div>

        <TokenHelp :tokens="tokens" class="max-w-3xl" />

        <EditorTabs ref="tabBar" v-model="tab" :tabs="tabs" :errors="form.errors as Record<string, string>" label="Template editor" />

        <section v-show="tab === 'sections'" role="tabpanel" class="space-y-4">
            <p class="max-w-3xl text-sm text-muted-foreground">
                Without a Hero, each page starts with “{service} in {city}, {state_code}”. Links to the service, the city and other services in the city, plus a closing call to action, are added automatically.
            </p>
            <SectionBuilder v-model="form.sections" :schema="sectionSchema" :options="sectionOptions" :errors="form.errors as Record<string, string>" />
        </section>

        <section v-show="tab === 'faqs'" class="max-w-2xl space-y-3" role="tabpanel">
            <Heading variant="small" title="FAQs on every city × service page" />
            <RecordPicker id="template-faqs" v-model="form.faq_ids" :options="sectionOptions.faqs" noun="FAQs" />
        </section>

        <section v-show="tab === 'seo'" class="max-w-3xl space-y-4" role="tabpanel">
            <p class="text-sm text-muted-foreground">
                Write the meta title and description with tokens, e.g. “{service} in {city}, {state_code} | {company}”. The preview shows them unreplaced. The canonical URL is ignored here: each page is its own canonical.
            </p>
            <SeoPanel v-model="form.seo" :fallbacks="seo.fallbacks" :url="seo.url" :errors="form.errors as Record<string, string>" />
        </section>
    </div>
</template>
