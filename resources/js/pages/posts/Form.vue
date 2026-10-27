<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Eye, ExternalLink, Trash2, X } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ArticleEditor from '@/components/editor/ArticleEditor.vue';
import EditorTabs from '@/components/editor/EditorTabs.vue';
import Heading from '@/components/Heading.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
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
import { create as createAuthor } from '@/routes/admin/authors';
import { destroy, index, preview, store, update } from '@/routes/admin/posts';

type PostData = {
    id: number;
    title: string;
    slug: string;
    category_id: number;
    author_id: number | null;
    excerpt: string | null;
    content: string | null;
    featured_media_id: number | null;
    status: 'draft' | 'published';
    state: 'draft' | 'scheduled' | 'published';
    published_at: string | null;
    is_featured: boolean;
    tags: string[];
    reading_minutes: number;
    path: string;
};

type Option = { id: number; name: string };

const props = defineProps<{
    post: PostData | null;
    categories: Option[];
    authors: Option[];
    allTags: string[];
    timezone: string;
    now: string;
    seo: SeoProps;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Blog Posts', href: index() }] },
});

const isNew = computed(() => props.post === null);
const siteUrl = computed(() => (usePage().props.seoDefaults as SeoDefaults).site_url.replace(/\/$/, ''));

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    category_id: props.post?.category_id ?? props.categories[0]?.id ?? null,
    author_id: props.post?.author_id ?? (props.authors.length === 1 ? props.authors[0].id : null),
    excerpt: props.post?.excerpt ?? '',
    content: props.post?.content ?? null,
    featured_media_id: props.post?.featured_media_id ?? null,
    status: props.post?.status ?? 'draft',
    published_at: props.post?.published_at ?? '',
    is_featured: props.post?.is_featured ?? false,
    tags: props.post?.tags ?? [],
    seo: { ...props.seo.values },
});

useUnsavedGuard(() => form.isDirty && !form.processing);

const slugTouched = ref(!isNew.value);
watch(
    () => form.title,
    (title) => !slugTouched.value && (form.slug = slugify(title)),
);

type Tab = 'content' | 'seo';
const tab = ref<Tab>('content');
const tabs: { key: Tab; label: string; match?: (k: string) => boolean }[] = [
    { key: 'content', label: 'Content' },
    { key: 'seo', label: 'SEO', match: (k) => k.startsWith('seo.') },
];
const tabBar = useTemplateRef<{ focusErrors: () => void }>('tabBar');

// Publishing: "Published" with a future date means scheduled.
const isFuture = computed(() => form.status === 'published' && !!form.published_at && form.published_at > props.now);
const saveLabel = computed(() => {
    if (form.status === 'draft') return 'Save draft';
    if (isFuture.value) return 'Schedule';

    return props.post?.state === 'published' ? 'Update' : 'Publish';
});
const stateBadge = computed(() => {
    const state = props.post?.state ?? 'draft';

    return { draft: 'Draft', scheduled: 'Scheduled', published: 'Published' }[state];
});

// Tags: type and press Enter or comma; suggestions from existing tags.
const tagInput = ref('');
function addTag(raw = tagInput.value) {
    raw.split(',').map((t) => t.trim()).filter(Boolean).forEach((name) => {
        if (form.tags.length < 10 && !form.tags.some((t) => t.toLowerCase() === name.toLowerCase())) {
            form.tags.push(name);
        }
    });
    tagInput.value = '';
}
function onTagKey(e: KeyboardEvent) {
    if (e.key === ',') {
        e.preventDefault();
        addTag();
    }
}

const statusOptions: { value: 'draft' | 'published'; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
];

const tagSuggestions = computed(() => props.allTags.filter((t) => !form.tags.some((x) => x.toLowerCase() === t.toLowerCase())));

const words = ref(0);

function submit() {
    form.transform((data) => ({ ...data, published_at: data.published_at || null }));

    const options = { preserveScroll: true, onSuccess: () => form.defaults(), onError: () => tabBar.value?.focusErrors() };

    if (props.post) {
        form.put(update(props.post.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

const confirmingDelete = ref(false);
const deletePost = () => props.post && router.delete(destroy(props.post.id));

const title = computed(() => (isNew.value ? 'New post' : form.title || 'Untitled post'));
const selectClass = 'h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs';
const errors = computed(() => form.errors as Record<string, string | undefined>);
const tagErrors = computed(() => Object.entries(errors.value).filter(([k]) => k.startsWith('tags')).map(([, v]) => v));
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link :href="index()" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" /> All posts
                </Link>
                <div class="flex flex-wrap items-center gap-2">
                    <Heading :title="title" class="mb-0!" />
                    <Badge v-if="post" :variant="post.state === 'published' ? 'default' : 'outline'">{{ stateBadge }}</Badge>
                    <Badge v-if="!form.seo.robots_index" variant="destructive">NOINDEX</Badge>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="form.isDirty" class="text-sm text-muted-foreground">Unsaved changes</span>
                <Button v-if="post && post.state === 'published'" variant="ghost" as-child>
                    <a :href="post.path" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View</a>
                </Button>
                <Button v-else-if="post" variant="ghost" as-child :title="form.isDirty ? 'Save first: the preview shows the saved version' : undefined">
                    <a :href="preview(post.id).url" target="_blank" rel="noopener"><Eye class="size-4" /> Preview</a>
                </Button>
                <Button type="button" :disabled="form.processing" @click="submit">{{ saveLabel }}</Button>
            </div>
        </div>

        <EditorTabs ref="tabBar" v-model="tab" :tabs="tabs" :errors="errors" label="Post editor" />

        <!-- Content -->
        <div v-show="tab === 'content'" class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]" role="tabpanel">
            <div class="min-w-0 space-y-6">
                <div class="grid gap-2">
                    <Label for="post-title">Title <span class="text-destructive">*</span></Label>
                    <Input id="post-title" v-model="form.title" maxlength="160" class="h-11 text-lg font-semibold" placeholder="The Ultimate Moving Checklist" :aria-invalid="!!form.errors.title" />
                    <p class="text-xs text-muted-foreground">The post's H1 heading.</p>
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="post-slug">URL</Label>
                    <div class="flex items-center rounded-md border shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50">
                        <span class="truncate pl-3 font-mono text-sm text-muted-foreground">{{ siteUrl }}/blog/</span>
                        <input
                            id="post-slug"
                            v-model="form.slug"
                            class="h-9 min-w-0 flex-1 bg-transparent pr-3 font-mono text-sm outline-none"
                            :aria-invalid="!!form.errors.slug"
                            @input="slugTouched = true"
                            @blur="form.slug = slugify(form.slug)"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">Changing it after publishing redirects the old URL automatically.</p>
                    <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="post-excerpt">Excerpt</Label>
                    <Textarea id="post-excerpt" v-model="form.excerpt" rows="2" maxlength="300" placeholder="One or two sentences that sum up the post." />
                    <p class="text-xs text-muted-foreground">Shown under the title, on blog cards and as the default meta description. {{ form.excerpt?.length ?? 0 }}/300</p>
                    <p v-if="form.errors.excerpt" class="text-sm text-destructive">{{ form.errors.excerpt }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="post-content">Article</Label>
                    <ArticleEditor id="post-content" v-model="form.content" :invalid="!!form.errors.content" @words="(n) => (words = n)" />
                    <p v-if="form.errors.content" class="text-sm text-destructive">{{ form.errors.content }}</p>
                </div>
            </div>

            <aside class="space-y-6">
                <fieldset class="space-y-4 rounded-xl border p-4">
                    <legend class="px-1 text-sm font-semibold">Publishing</legend>
                    <div class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1" role="radiogroup" aria-label="Status">
                        <button
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            type="button"
                            role="radio"
                            :aria-checked="form.status === opt.value"
                            class="rounded-md px-3 py-1.5 text-sm font-medium"
                            :class="form.status === opt.value ? 'bg-background shadow-sm' : 'text-muted-foreground'"
                            @click="form.status = opt.value"
                        >
                            {{ opt.label }}
                        </button>
                    </div>

                    <div class="grid gap-2">
                        <Label for="post-date">Publish date</Label>
                        <Input id="post-date" v-model="form.published_at" type="datetime-local" :aria-invalid="!!form.errors.published_at" />
                        <p class="text-xs text-muted-foreground">
                            <template v-if="form.status === 'draft'">Not on the website while it is a draft.</template>
                            <template v-else-if="isFuture">Scheduled: goes live by itself at this time.</template>
                            <template v-else-if="!form.published_at">Leave empty to publish now.</template>
                            <template v-else>Live on the website.</template>
                            Time zone: {{ timezone }}.
                        </p>
                        <p v-if="form.errors.published_at" class="text-sm text-destructive">{{ form.errors.published_at }}</p>
                    </div>

                    <label class="flex items-center gap-3 text-sm">
                        <Checkbox :model-value="form.is_featured" @update:model-value="(v) => (form.is_featured = v === true)" />
                        Featured (big card at the top of the blog)
                    </label>

                    <p v-if="post" class="text-xs text-muted-foreground">{{ Math.max(1, Math.ceil(words / 200)) }} min read (calculated automatically).</p>
                </fieldset>

                <div class="grid gap-2">
                    <Label for="post-category">Category <span class="text-destructive">*</span></Label>
                    <select id="post-category" v-model="form.category_id" :class="selectClass" :aria-invalid="!!form.errors.category_id">
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="form.errors.category_id" class="text-sm text-destructive">{{ form.errors.category_id }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="post-author">Author</Label>
                    <select id="post-author" v-model="form.author_id" :class="selectClass">
                        <option :value="null">No author</option>
                        <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <p v-if="!authors.length" class="text-xs text-muted-foreground">
                        <Link :href="createAuthor()" class="underline">Add an author</Link> to show a byline and author box (good for Google E-E-A-T).
                    </p>
                </div>

                <div class="grid gap-2">
                    <span class="text-sm font-medium">Featured image</span>
                    <MediaPicker v-model="form.featured_media_id" preset="blog" label="Featured image" />
                    <p class="text-xs text-muted-foreground">Top of the post, blog cards and the social share image.</p>
                </div>

                <div class="grid gap-2">
                    <Label for="post-tags">Tags</Label>
                    <Input
                        id="post-tags"
                        v-model="tagInput"
                        list="post-tag-options"
                        placeholder="Type a tag and press Enter"
                        :disabled="form.tags.length >= 10"
                        @keydown.enter.prevent="addTag()"
                        @keydown="onTagKey"
                        @change="tagSuggestions.includes(tagInput) && addTag()"
                    />
                    <datalist id="post-tag-options">
                        <option v-for="t in tagSuggestions" :key="t" :value="t" />
                    </datalist>
                    <div v-if="form.tags.length" class="flex flex-wrap gap-1.5">
                        <span v-for="t in form.tags" :key="t" class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs">
                            {{ t }}
                            <button type="button" :aria-label="`Remove tag ${t}`" @click="form.tags = form.tags.filter((x) => x !== t)"><X class="size-3" /></button>
                        </span>
                    </div>
                    <p v-for="(msg, i) in tagErrors" :key="i" class="text-sm text-destructive">{{ msg }}</p>
                </div>

                <Button v-if="post" type="button" variant="ghost" class="text-destructive" @click="confirmingDelete = true">
                    <Trash2 class="size-4" /> Delete post
                </Button>
            </aside>
        </div>

        <!-- SEO -->
        <section v-show="tab === 'seo'" class="max-w-3xl" role="tabpanel">
            <SeoPanel
                v-model="form.seo"
                :fallbacks="{ ...seo.fallbacks, description: form.excerpt || seo.fallbacks.description, image_id: form.featured_media_id ?? seo.fallbacks.image_id }"
                :url="post ? seo.url : `${siteUrl}/blog/${form.slug || 'your-post'}`"
                :record-title="form.title"
                :errors="errors as Record<string, string>"
            />
        </section>
    </div>

    <ConfirmDialog
        :open="confirmingDelete"
        :title="`Delete “${form.title}”?`"
        description="The post is removed from the website and the blog."
        confirm-label="Delete"
        destructive
        @update:open="(o) => (confirmingDelete = o)"
        @confirm="deletePost"
    />
</template>
