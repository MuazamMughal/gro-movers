<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import EditorTabs from '@/components/editor/EditorTabs.vue';
import Heading from '@/components/Heading.vue';
import SeoPanel from '@/components/seo/SeoPanel.vue';
import type { SeoDefaults, SeoProps } from '@/components/seo/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { slugify } from '@/lib/slug';
import { index, store, update } from '@/routes/admin/blog-categories';

type CategoryData = { id: number; name: string; slug: string; description: string | null; path: string; posts_count: number };

const props = defineProps<{ category: CategoryData | null; seo: SeoProps }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Blog Categories', href: index() }] },
});

const isNew = computed(() => props.category === null);
const siteUrl = computed(() => (usePage().props.seoDefaults as SeoDefaults).site_url.replace(/\/$/, ''));

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    seo: { ...props.seo.values },
});

useUnsavedGuard(() => form.isDirty && !form.processing);

const slugTouched = ref(!isNew.value);
watch(
    () => form.name,
    (name) => !slugTouched.value && (form.slug = slugify(name)),
);

type Tab = 'details' | 'seo';
const tab = ref<Tab>('details');
const tabs: { key: Tab; label: string; match?: (k: string) => boolean }[] = [
    { key: 'details', label: 'Details' },
    { key: 'seo', label: 'SEO', match: (k) => k.startsWith('seo.') },
];
const tabBar = useTemplateRef<{ focusErrors: () => void }>('tabBar');

function submit() {
    const options = { preserveScroll: true, onSuccess: () => form.defaults(), onError: () => tabBar.value?.focusErrors() };

    if (props.category) {
        form.put(update(props.category.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

const title = computed(() => (isNew.value ? 'New category' : form.name || 'Untitled category'));
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" /> All categories
                </Link>
                <div class="flex flex-wrap items-center gap-2">
                    <Heading :title="title" class="mb-0!" />
                    <Badge v-if="!form.seo.robots_index" variant="destructive">NOINDEX</Badge>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button v-if="category" variant="ghost" as-child>
                    <a :href="category.path" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a>
                </Button>
                <Button type="button" :disabled="form.processing" @click="submit">{{ isNew ? 'Create category' : 'Save' }}</Button>
            </div>
        </div>

        <EditorTabs ref="tabBar" v-model="tab" :tabs="tabs" :errors="form.errors as Record<string, string>" label="Category editor" />

        <section v-show="tab === 'details'" class="max-w-2xl space-y-6" role="tabpanel">
            <div class="grid gap-2">
                <Label for="category-name">Name <span class="text-destructive">*</span></Label>
                <Input id="category-name" v-model="form.name" maxlength="80" placeholder="Moving Tips" :aria-invalid="!!form.errors.name" />
                <p class="text-xs text-muted-foreground">The H1 of the category page and the label in the blog's category menu.</p>
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="category-slug">URL</Label>
                <div class="flex items-center rounded-md border shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50">
                    <span class="truncate pl-3 font-mono text-sm text-muted-foreground">{{ siteUrl }}/blog/category/</span>
                    <input
                        id="category-slug"
                        v-model="form.slug"
                        class="h-9 min-w-0 flex-1 bg-transparent pr-3 font-mono text-sm outline-none"
                        :aria-invalid="!!form.errors.slug"
                        @input="slugTouched = true"
                        @blur="form.slug = slugify(form.slug)"
                    />
                </div>
                <p class="text-xs text-muted-foreground">Changing it later redirects the old URL automatically.</p>
                <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="category-description">Description</Label>
                <Textarea id="category-description" v-model="form.description" rows="3" maxlength="500" />
                <p class="text-xs text-muted-foreground">Shown under the heading and used as the default meta description. {{ form.description?.length ?? 0 }}/500</p>
                <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
            </div>
        </section>

        <section v-show="tab === 'seo'" class="max-w-3xl" role="tabpanel">
            <SeoPanel
                v-model="form.seo"
                :fallbacks="{ ...seo.fallbacks, description: form.description || seo.fallbacks.description }"
                :url="category ? seo.url : `${siteUrl}/blog/category/${form.slug || 'your-category'}`"
                :record-title="form.name"
                :errors="form.errors as Record<string, string>"
            />
        </section>
    </div>
</template>
