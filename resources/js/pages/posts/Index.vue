<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Eye, Newspaper, Pencil, Plus, Search, Star } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, featured, index, preview } from '@/routes/admin/posts';

type PostRow = {
    id: number;
    title: string;
    path: string;
    state: 'draft' | 'scheduled' | 'published';
    published_at: string | null;
    category: string | null;
    author: string | null;
    is_featured: boolean;
    reading_minutes: number;
    thumb: string | null;
    noindex: boolean;
    seo_missing: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    posts: Paginated<PostRow>;
    filters: { q: string; state: string; category: string };
    categories: { id: number; name: string }[];
    counts: { all: number; published: number; scheduled: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Blog Posts', href: index() }] },
});

const q = ref(props.filters.q);
const state = ref(props.filters.state);
const category = ref(props.filters.category);

const apply = () =>
    router.get(
        index().url,
        Object.fromEntries(Object.entries({ q: q.value, state: state.value, category: category.value }).filter(([, v]) => v)),
        { preserveState: true, preserveScroll: true, replace: true },
    );

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch([state, category], apply);

const toggleFeatured = (post: PostRow) => router.patch(featured(post.id).url, {}, { preserveScroll: true });

const stateLabels = { draft: 'Draft', scheduled: 'Scheduled', published: 'Published' } as const;
const stateVariants = { draft: 'outline', scheduled: 'secondary', published: 'default' } as const;
const selectClass = 'h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Head title="Blog Posts" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Blog Posts"
                :description="`${counts.published} published · ${counts.scheduled} scheduled · ${counts.all} in total. Posts live at /blog/…`"
            />
            <Button as-child>
                <Link :href="create()"><Plus class="size-4" /> New post</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="relative min-w-60 flex-1">
                <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                <Input v-model="q" class="pl-8" placeholder="Search by title or URL" aria-label="Search posts" />
            </div>
            <select v-model="state" :class="selectClass" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="published">Published</option>
                <option value="scheduled">Scheduled</option>
                <option value="draft">Drafts</option>
            </select>
            <select v-model="category" :class="selectClass" aria-label="Filter by category">
                <option value="">All categories</option>
                <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
        </div>

        <div v-if="!posts.data.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <Newspaper class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">{{ counts.all ? 'No posts match these filters' : 'No posts yet' }}</p>
            <p class="text-sm text-muted-foreground">Moving tips, packing guides and cost guides bring visitors from Google.</p>
        </div>

        <ul v-else class="divide-y overflow-hidden rounded-xl border">
            <li v-for="post in posts.data" :key="post.id" class="flex items-center gap-3 px-3 py-2.5">
                <div class="hidden h-12 w-20 shrink-0 overflow-hidden rounded bg-muted sm:block">
                    <img v-if="post.thumb" :src="post.thumb" alt="" class="size-full object-cover" loading="lazy" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="edit(post.id)" class="truncate font-medium hover:underline">{{ post.title }}</Link>
                        <Badge :variant="stateVariants[post.state]">{{ stateLabels[post.state] }}</Badge>
                        <Badge v-if="post.noindex" variant="destructive">NOINDEX</Badge>
                        <Badge v-if="post.seo_missing" variant="outline" class="text-amber-600 dark:text-amber-400">SEO not set</Badge>
                    </div>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ post.category ?? 'No category' }}
                        <template v-if="post.author"> · {{ post.author }}</template>
                        <template v-if="post.published_at"> · {{ post.state === 'scheduled' ? 'Goes live' : 'Published' }} {{ post.published_at }}</template>
                        · {{ post.reading_minutes }} min read
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :aria-label="post.is_featured ? `Unfeature ${post.title}` : `Feature ${post.title}`"
                    :title="post.is_featured ? 'Featured on the blog (click to remove)' : 'Feature on the blog'"
                    @click="toggleFeatured(post)"
                >
                    <Star class="size-4" :class="post.is_featured ? 'fill-amber-400 text-amber-500' : 'text-muted-foreground'" />
                </Button>
                <Button v-if="post.state === 'published'" variant="ghost" size="icon" class="size-8" as-child>
                    <a :href="post.path" target="_blank" rel="noopener" :aria-label="`View ${post.title}`"><ExternalLink class="size-4" /></a>
                </Button>
                <Button v-else variant="ghost" size="icon" class="size-8" as-child>
                    <a :href="preview(post.id).url" target="_blank" rel="noopener" :aria-label="`Preview ${post.title}`"><Eye class="size-4" /></a>
                </Button>
                <Button variant="ghost" size="icon" class="size-8" as-child>
                    <Link :href="edit(post.id)" :aria-label="`Edit ${post.title}`"><Pencil class="size-4" /></Link>
                </Button>
            </li>
        </ul>

        <nav v-if="posts.last_page > 1" class="flex items-center justify-between text-sm" aria-label="Pagination">
            <Button variant="outline" size="sm" :disabled="!posts.prev_page_url" as-child>
                <Link v-if="posts.prev_page_url" :href="posts.prev_page_url" preserve-scroll>← Newer</Link>
                <span v-else>← Newer</span>
            </Button>
            <span class="text-muted-foreground">Page {{ posts.current_page }} of {{ posts.last_page }} · {{ posts.total }} posts</span>
            <Button variant="outline" size="sm" :disabled="!posts.next_page_url" as-child>
                <Link v-if="posts.next_page_url" :href="posts.next_page_url" preserve-scroll>Older →</Link>
                <span v-else>Older →</span>
            </Button>
        </nav>
    </div>
</template>
