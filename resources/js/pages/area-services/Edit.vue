<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink } from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import EditorTabs from '@/components/editor/EditorTabs.vue';
import TokenHelp from '@/components/editor/TokenHelp.vue';
import Heading from '@/components/Heading.vue';
import RecordPicker from '@/components/sections/RecordPicker.vue';
import SectionBuilder from '@/components/sections/SectionBuilder.vue';
import type { SectionItem, SectionOptions, SectionSchema } from '@/components/sections/types';
import { clone, uid } from '@/components/sections/types';
import SeoPanel from '@/components/seo/SeoPanel.vue';
import type { SeoProps } from '@/components/seo/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { index, template, update } from '@/routes/admin/area-services';
import { index as areasIndex } from '@/routes/admin/areas';

const props = defineProps<{
    page: {
        id: number;
        heading: string;
        title_override: string | null;
        use_template: boolean;
        is_active: boolean;
        path: string;
        area: { id: number; name: string };
        service: { id: number; title: string };
        faq_ids: number[];
    };
    templateSections: SectionItem[];
    tokens: Record<string, string>;
    seo: SeoProps;
    sections: SectionItem[];
    sectionSchema: SectionSchema;
    sectionOptions: SectionOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Service Areas', href: areasIndex() },
            { title: 'City × service pages', href: index() },
        ],
    },
});

const form = useForm({
    title_override: props.page.title_override ?? '',
    use_template: props.page.use_template,
    is_active: props.page.is_active,
    faq_ids: props.page.faq_ids,
    seo: { ...props.seo.values },
    sections: props.sections.map((s) => ({ ...s, _key: uid() })),
});

useUnsavedGuard(() => form.isDirty && !form.processing);

/** Switching to custom content starts from a copy of the template, not an empty page. */
function useCustom() {
    form.use_template = false;

    if (!form.sections.length) {
        form.sections = props.templateSections.map((s) => ({ ...clone(s), id: null, _key: uid() }));
    }
}

type Tab = 'content' | 'faqs' | 'seo';
const tab = ref<Tab>('content');
const tabs = computed<{ key: Tab; label: string; match?: (k: string) => boolean }[]>(() =>
    form.use_template
        ? [{ key: 'content', label: 'Content' }]
        : [
              { key: 'content', label: 'Content' },
              { key: 'faqs', label: 'FAQs', match: (k) => k.startsWith('faq_ids') },
              { key: 'seo', label: 'SEO', match: (k) => k.startsWith('seo.') },
          ],
);
const tabBar = useTemplateRef('tabBar');

const submit = () =>
    form
        .transform((data) => ({ ...data, title_override: data.title_override || null, sections: data.sections.map(({ _key, ...s }) => s) }))
        .put(update(props.page.id).url, { preserveScroll: true, onSuccess: () => form.defaults(), onError: () => tabBar.value?.focusErrors() });

const title = computed(() => `${props.page.service.title} in ${props.page.area.name}`);
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft class="size-4" /> City × service pages</Link>
                <div class="flex flex-wrap items-center gap-2">
                    <Heading :title="title" class="mb-0!" />
                    <Badge :variant="form.is_active ? 'default' : 'outline'">{{ form.is_active ? 'Live' : 'Off' }}</Badge>
                    <Badge variant="secondary">{{ form.use_template ? 'Shared template' : 'Custom content' }}</Badge>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button v-if="page.is_active" variant="ghost" as-child><a :href="page.path" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a></Button>
                <Button type="button" :disabled="form.processing" @click="submit">Save</Button>
            </div>
        </div>

        <div class="grid max-w-3xl gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="asp-title">Page heading (H1)</Label>
                <Input id="asp-title" v-model="form.title_override" :placeholder="`${page.service.title} in ${page.area.name}`" maxlength="120" />
                <p class="text-xs text-muted-foreground">Leave empty for the automatic heading.</p>
            </div>
            <label class="flex items-center gap-3 self-center text-sm font-medium">
                <Checkbox :model-value="form.is_active" @update:model-value="(v) => (form.is_active = v === true)" />
                Page is live on the website
            </label>
        </div>

        <div class="flex max-w-3xl flex-wrap items-center gap-3 rounded-lg border p-4">
            <div class="flex-1 text-sm">
                <p class="font-medium">{{ form.use_template ? 'This page uses the shared template.' : 'This page has its own content and SEO.' }}</p>
                <p class="text-muted-foreground">
                    {{ form.use_template ? 'Customize it to write unique local content — best for your most important cities.' : 'Switching back keeps this content saved, but the template is shown instead.' }}
                </p>
            </div>
            <Button v-if="form.use_template" type="button" variant="outline" @click="useCustom">Customize this page</Button>
            <template v-else>
                <Button type="button" variant="ghost" @click="form.use_template = true">Use the template again</Button>
            </template>
            <Button v-if="form.use_template" variant="ghost" as-child><Link :href="template()">Edit template</Link></Button>
        </div>

        <template v-if="!form.use_template">
            <TokenHelp :tokens="tokens" class="max-w-3xl" />
            <EditorTabs ref="tabBar" v-model="tab" :tabs="tabs" :errors="form.errors as Record<string, string>" label="Page editor" />

            <section v-show="tab === 'content'" role="tabpanel">
                <SectionBuilder v-model="form.sections" :schema="sectionSchema" :options="sectionOptions" :errors="form.errors as Record<string, string>" />
            </section>
            <section v-show="tab === 'faqs'" class="max-w-2xl" role="tabpanel">
                <RecordPicker id="asp-faqs" v-model="form.faq_ids" :options="sectionOptions.faqs" noun="FAQs" />
            </section>
            <section v-show="tab === 'seo'" class="max-w-3xl" role="tabpanel">
                <SeoPanel v-model="form.seo" :fallbacks="seo.fallbacks" :url="seo.url" :record-title="form.title_override || `${page.service.title} in ${page.area.name}`" :errors="form.errors as Record<string, string>" />
            </section>
        </template>
    </div>
</template>
