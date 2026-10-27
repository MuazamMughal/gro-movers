<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Plus, Search, Star } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { create, edit, index, toggle } from '@/routes/admin/reviews';

type ReviewRow = {
    id: number;
    name: string;
    city: string | null;
    rating: number;
    body: string;
    source: string;
    reviewed_at: string | null;
    area: string | null;
    service: string | null;
    is_featured: boolean;
    is_active: boolean;
    thumb: string | null;
};

type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number; prev_page_url: string | null; next_page_url: string | null };

const props = defineProps<{
    reviews: Paginated<ReviewRow>;
    filters: { q: string; source: string; area: string };
    sources: Record<string, string>;
    areas: { id: number; name: string }[];
    stats: { count: number; average: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reviews', href: index() }] },
});

const q = ref(props.filters.q);
const source = ref(props.filters.source);
const area = ref(props.filters.area);

const apply = () =>
    router.get(
        index().url,
        Object.fromEntries(Object.entries({ q: q.value, source: source.value, area: area.value }).filter(([, v]) => v)),
        { preserveState: true, preserveScroll: true, replace: true },
    );

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch([source, area], apply);

const flip = (review: ReviewRow, field: 'is_active' | 'is_featured') => router.patch(toggle(review.id).url, { field }, { preserveScroll: true });

const selectClass = 'h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs';
const stars = [1, 2, 3, 4, 5];
</script>

<template>
    <Head title="Reviews" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Reviews"
                :description="stats.count ? `${stats.count} on the website · ${stats.average.toFixed(1)} average. Featured reviews show first in review sliders.` : 'Add customer reviews to show on /reviews, the home page and city pages.'"
            />
            <div class="flex gap-2">
                <Button variant="ghost" as-child>
                    <a href="/reviews" target="_blank" rel="noopener"><ExternalLink class="size-4" /> View page</a>
                </Button>
                <Button as-child>
                    <Link :href="create()"><Plus class="size-4" /> New review</Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="relative min-w-60 flex-1">
                <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                <Input v-model="q" class="pl-8" placeholder="Search by name or text" aria-label="Search reviews" />
            </div>
            <select v-model="source" :class="selectClass" aria-label="Filter by source">
                <option value="">All sources</option>
                <option v-for="(label, value) in sources" :key="value" :value="value">{{ label }}</option>
            </select>
            <select v-model="area" :class="selectClass" aria-label="Filter by city">
                <option value="">All cities</option>
                <option v-for="a in areas" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
            </select>
        </div>

        <div v-if="!reviews.data.length" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center">
            <Star class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">{{ stats.count || filters.q || filters.source || filters.area ? 'No reviews match these filters' : 'No reviews yet' }}</p>
        </div>

        <ul v-else class="divide-y overflow-hidden rounded-xl border">
            <li v-for="review in reviews.data" :key="review.id" class="flex items-start gap-3 px-3 py-3" :class="{ 'opacity-60': !review.is_active }">
                <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted font-semibold">
                    <img v-if="review.thumb" :src="review.thumb" alt="" class="size-full object-cover" />
                    <span v-else aria-hidden="true">{{ review.name.charAt(0) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="edit(review.id)" class="font-medium hover:underline">{{ review.name }}</Link>
                        <span class="flex text-amber-500" :aria-label="`${review.rating} out of 5 stars`">
                            <Star v-for="i in stars" :key="i" class="size-3.5" :class="i <= review.rating ? 'fill-current' : 'opacity-30'" aria-hidden="true" />
                        </span>
                        <Badge variant="outline">{{ review.source }}</Badge>
                        <Badge v-if="review.is_featured">Featured</Badge>
                    </div>
                    <p class="line-clamp-2 text-sm text-muted-foreground">{{ review.body }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ [review.city, review.reviewed_at, review.area && `City page: ${review.area}`, review.service && `Service: ${review.service}`].filter(Boolean).join(' · ') }}
                    </p>
                </div>
                <label class="flex shrink-0 items-center gap-2 text-sm">
                    <Checkbox :model-value="review.is_active" @update:model-value="flip(review, 'is_active')" /> Show
                </label>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    :aria-label="review.is_featured ? `Unfeature review from ${review.name}` : `Feature review from ${review.name}`"
                    :title="review.is_featured ? 'Featured (click to remove)' : 'Feature this review'"
                    @click="flip(review, 'is_featured')"
                >
                    <Star class="size-4" :class="review.is_featured ? 'fill-amber-400 text-amber-500' : 'text-muted-foreground'" />
                </Button>
                <Button variant="ghost" size="icon" class="size-8 shrink-0" as-child>
                    <Link :href="edit(review.id)" :aria-label="`Edit review from ${review.name}`"><Pencil class="size-4" /></Link>
                </Button>
            </li>
        </ul>

        <nav v-if="reviews.last_page > 1" class="flex items-center justify-between text-sm" aria-label="Pagination">
            <Button variant="outline" size="sm" :disabled="!reviews.prev_page_url" as-child>
                <Link v-if="reviews.prev_page_url" :href="reviews.prev_page_url" preserve-scroll>← Previous</Link>
                <span v-else>← Previous</span>
            </Button>
            <span class="text-muted-foreground">Page {{ reviews.current_page }} of {{ reviews.last_page }}</span>
            <Button variant="outline" size="sm" :disabled="!reviews.next_page_url" as-child>
                <Link v-if="reviews.next_page_url" :href="reviews.next_page_url" preserve-scroll>Next →</Link>
                <span v-else>Next →</span>
            </Button>
        </nav>
    </div>
</template>
